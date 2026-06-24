package com.ekidio.app;

import android.Manifest;
import android.app.Activity;
import android.content.pm.PackageManager;
import android.graphics.ImageFormat;
import android.os.Handler;
import android.os.Looper;
import android.os.SystemClock;
import android.util.Size;

import androidx.annotation.NonNull;
import androidx.camera.core.CameraSelector;
import androidx.camera.core.ImageAnalysis;
import androidx.camera.core.ImageProxy;
import androidx.camera.lifecycle.ProcessCameraProvider;
import androidx.core.content.ContextCompat;
import androidx.lifecycle.LifecycleOwner;

import java.nio.ByteBuffer;

import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.atomic.AtomicBoolean;

/**
 * Screen off after idle timeout; reset timer on touch or camera motion while awake.
 */
public class KioskMotionDetector {

  private static final int ANALYSIS_WIDTH = 320;
  private static final int ANALYSIS_HEIGHT = 240;
  private static final int PIXEL_DIFF_THRESHOLD = 32;
  private static final int PIXEL_SAMPLE_STEP = 3;
  /** Motion while screen is on – reset idle timer. */
  private static final double IDLE_MOTION_RATIO = 0.035;
  /** Motion while screen is off – wake display. */
  private static final double WAKE_MOTION_RATIO = 0.04;
  private static final int MOTION_STREAK_REQUIRED = 2;
  private static final long MIN_ANALYSIS_INTERVAL_MS = 300;

  private final Activity activity;
  private final KioskScreenController screenController;
  private final Handler mainHandler = new Handler(Looper.getMainLooper());
  private final ExecutorService analysisExecutor = Executors.newSingleThreadExecutor();
  private final AtomicBoolean running = new AtomicBoolean(false);

  private int idleTimeoutMs = 15000;
  private long lastAnalysisAt = 0L;
  private boolean screenAwake = true;
  private int motionStreak = 0;
  private byte[] previousFrame = null;

  private ProcessCameraProvider cameraProvider = null;

  private final Runnable turnOffRunnable = new Runnable() {
    @Override
    public void run() {
      if (!running.get() || !screenAwake) {
        return;
      }
      screenAwake = false;
      motionStreak = 0;
      previousFrame = null;
      screenController.turnOff();
    }
  };

  public KioskMotionDetector(Activity activity) {
    this.activity = activity;
    this.screenController = new KioskScreenController(activity);
    screenController.setUserActivityCallback(() -> onUserActivity());
  }

  public void start(int idleTimeoutMs) {
    if (running.getAndSet(true)) {
      return;
    }
    this.idleTimeoutMs = Math.max(1000, idleTimeoutMs);
    screenAwake = true;
    motionStreak = 0;
    previousFrame = null;

    screenController.start();
    mainHandler.post(() -> {
      screenController.turnOn();
      scheduleTurnOff();
    });

    bindCameraIfPermitted();
  }

  void bindCameraIfPermitted() {
    if (!running.get() || !hasCameraPermission()) {
      return;
    }

    ProcessCameraProvider.getInstance(activity).addListener(() -> {
      try {
        cameraProvider = ProcessCameraProvider.getInstance(activity).get();
        bindCamera();
      } catch (Exception ignored) {
      }
    }, ContextCompat.getMainExecutor(activity));
  }

  public void stop() {
    if (!running.getAndSet(false)) {
      return;
    }
    mainHandler.removeCallbacks(turnOffRunnable);
    analysisExecutor.execute(() -> unbindCamera());
    mainHandler.post(() -> screenController.stop());
    previousFrame = null;
    motionStreak = 0;
  }

  private void scheduleTurnOff() {
    mainHandler.removeCallbacks(turnOffRunnable);
    if (!running.get() || !screenAwake) {
      return;
    }
    mainHandler.postDelayed(turnOffRunnable, idleTimeoutMs);
  }

  private void onUserActivity() {
    if (!running.get()) {
      return;
    }
    mainHandler.post(() -> {
      if (!screenAwake) {
        screenAwake = true;
        motionStreak = 0;
        previousFrame = null;
        screenController.turnOn();
      }
      scheduleTurnOff();
    });
  }

  void notifyUserActivity() {
    onUserActivity();
  }

  void updateIdleTimeout(int idleTimeoutMs) {
    this.idleTimeoutMs = Math.max(1000, idleTimeoutMs);
    if (running.get() && screenAwake) {
      scheduleTurnOff();
    }
  }

  private boolean hasCameraPermission() {
    return ContextCompat.checkSelfPermission(activity, Manifest.permission.CAMERA)
        == PackageManager.PERMISSION_GRANTED;
  }

  private void bindCamera() {
    if (cameraProvider == null || !running.get()) {
      return;
    }

    unbindCamera();

    ImageAnalysis analysis = new ImageAnalysis.Builder()
        .setTargetResolution(new Size(ANALYSIS_WIDTH, ANALYSIS_HEIGHT))
        .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
        .setOutputImageFormat(ImageAnalysis.OUTPUT_IMAGE_FORMAT_YUV_420_888)
        .build();

    analysis.setAnalyzer(analysisExecutor, this::analyzeFrame);

    CameraSelector selector = new CameraSelector.Builder()
        .requireLensFacing(CameraSelector.LENS_FACING_FRONT)
        .build();

    LifecycleOwner owner = (LifecycleOwner) activity;
    cameraProvider.bindToLifecycle(owner, selector, analysis);
  }

  private void unbindCamera() {
    if (cameraProvider != null) {
      try {
        cameraProvider.unbindAll();
      } catch (Exception ignored) {
      }
    }
  }

  private void analyzeFrame(@NonNull ImageProxy image) {
    if (!running.get()) {
      image.close();
      return;
    }

    long now = SystemClock.elapsedRealtime();
    if (now - lastAnalysisAt < MIN_ANALYSIS_INTERVAL_MS) {
      image.close();
      return;
    }
    lastAnalysisAt = now;

    try {
      if (image.getFormat() != ImageFormat.YUV_420_888) {
        return;
      }

      int width = image.getWidth();
      int height = image.getHeight();

      ImageProxy.PlaneProxy yPlane = image.getPlanes()[0];
      ByteBuffer yBuffer = yPlane.getBuffer();
      int yRowStride = yPlane.getRowStride();
      int yPixelStride = yPlane.getPixelStride();

      int sampled = 0;
      int changed = 0;
      byte[] current = previousFrame;
      boolean needNewBuffer = current == null || current.length != countSampledPixels(width, height);
      if (needNewBuffer) {
        current = new byte[countSampledPixels(width, height)];
      }

      int sampleIndex = 0;
      for (int row = 0; row < height; row += PIXEL_SAMPLE_STEP) {
        int rowOffset = row * yRowStride;
        for (int col = 0; col < width; col += PIXEL_SAMPLE_STEP) {
          byte value = yBuffer.get(rowOffset + col * yPixelStride);
          if (previousFrame != null && sampleIndex < previousFrame.length) {
            int diff = Math.abs(value - previousFrame[sampleIndex]);
            if (diff > PIXEL_DIFF_THRESHOLD) {
              changed++;
            }
          }
          current[sampleIndex++] = value;
          sampled++;
        }
      }

      if (previousFrame != null && sampled > 0) {
        double ratio = (double) changed / sampled;
        double threshold = screenAwake ? IDLE_MOTION_RATIO : WAKE_MOTION_RATIO;
        if (ratio >= threshold) {
          motionStreak++;
          if (motionStreak >= MOTION_STREAK_REQUIRED) {
            motionStreak = 0;
            onUserActivity();
          }
        } else {
          motionStreak = 0;
        }
      }

      previousFrame = current;
    } catch (Exception ignored) {
    } finally {
      image.close();
    }
  }

  private int countSampledPixels(int width, int height) {
    int rows = 0;
    for (int row = 0; row < height; row += PIXEL_SAMPLE_STEP) {
      rows++;
    }
    int cols = 0;
    for (int col = 0; col < width; col += PIXEL_SAMPLE_STEP) {
      cols++;
    }
    return rows * cols;
  }
}
