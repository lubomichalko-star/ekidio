package com.ekidio.app;

import android.Manifest;

import com.getcapacitor.JSObject;
import com.getcapacitor.PermissionState;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.annotation.Permission;
import com.getcapacitor.annotation.PermissionCallback;

@CapacitorPlugin(
    name = "KioskMotion",
    permissions = {
        @Permission(
            alias = "camera",
            strings = { Manifest.permission.CAMERA }
        )
    }
)
public class KioskMotionPlugin extends Plugin {

  private static final int DEFAULT_IDLE_MS = 15000;
  private KioskMotionDetector detector = null;

  private int readIdleTimeoutMs(PluginCall call) {
    if (call == null) {
      return DEFAULT_IDLE_MS;
    }

    JSObject data = call.getData();
    if (data != null) {
      try {
        if (data.has("idleTimeoutMs")) {
          double ms = data.getDouble("idleTimeoutMs");
          if (ms > 0) {
            return (int) Math.max(1000, Math.round(ms));
          }
        }
      } catch (Exception ignored) {
      }
      try {
        if (data.has("idleTimeoutSec")) {
          int sec = data.getInteger("idleTimeoutSec");
          if (sec > 0) {
            return Math.max(1000, sec * 1000);
          }
        }
      } catch (Exception ignored) {
      }
    }

    try {
      Double asDouble = call.getDouble("idleTimeoutMs");
      if (asDouble != null && asDouble > 0) {
        return (int) Math.max(1000, Math.round(asDouble));
      }
    } catch (Exception ignored) {
    }
    try {
      Integer asInt = call.getInt("idleTimeoutMs");
      if (asInt != null && asInt > 0) {
        return Math.max(1000, asInt);
      }
    } catch (Exception ignored) {
    }
    return DEFAULT_IDLE_MS;
  }

  @PluginMethod
  public void start(PluginCall call) {
    if (detector != null) {
      detector.stop();
      detector = null;
    }

    int idleMs = readIdleTimeoutMs(call);
    startDetector(idleMs, call);
  }

  @PermissionCallback
  private void cameraPermsCallback(PluginCall call) {
    if (detector != null && getPermissionState("camera") == PermissionState.GRANTED) {
      detector.bindCameraIfPermitted();
    }
  }

  private void startDetector(int idleMs, PluginCall call) {
    try {
      detector = new KioskMotionDetector(getActivity());
      detector.start(idleMs);
      if (call != null) {
        JSObject ret = new JSObject();
        ret.put("idleTimeoutMs", idleMs);
        call.resolve(ret);
      }
      if (getPermissionState("camera") != PermissionState.GRANTED) {
        requestPermissionForAlias("camera", null, "cameraPermsCallback");
      }
    } catch (Exception e) {
      detector = null;
      if (call != null) {
        call.reject(e.getMessage() != null ? e.getMessage() : "Failed to start motion detector", e);
      }
    }
  }

  @PluginMethod
  public void notifyUserActivity(PluginCall call) {
    if (detector != null) {
      detector.notifyUserActivity();
    }
    call.resolve();
  }

  @PluginMethod
  public void updateIdleTimeout(PluginCall call) {
    if (detector == null) {
      call.resolve();
      return;
    }
    int idleMs = readIdleTimeoutMs(call);
    detector.updateIdleTimeout(idleMs);
    JSObject ret = new JSObject();
    ret.put("idleTimeoutMs", idleMs);
    call.resolve(ret);
  }

  @PluginMethod
  public void stop(PluginCall call) {
    if (detector != null) {
      detector.stop();
      detector = null;
    }
    call.resolve();
  }

  @PluginMethod
  public void isRunning(PluginCall call) {
    JSObject ret = new JSObject();
    ret.put("running", detector != null);
    call.resolve(ret);
  }

  @Override
  protected void handleOnDestroy() {
    if (detector != null) {
      detector.stop();
      detector = null;
    }
    super.handleOnDestroy();
  }
}
