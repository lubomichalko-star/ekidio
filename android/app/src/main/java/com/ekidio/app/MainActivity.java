package com.ekidio.app;

import android.os.Build;
import android.os.Bundle;
import androidx.core.view.WindowCompat;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
  @Override
  public void onCreate(Bundle savedInstanceState) {
    registerPlugin(KioskMotionPlugin.class);
    super.onCreate(savedInstanceState);

    // Allow kiosk motion wake to turn the display on from a fully off state.
    if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O_MR1) {
      setShowWhenLocked(true);
      setTurnScreenOn(true);
    }

    // Vynutime, aby sa okno neprekryvalo so status barom (System Bars)
    // Toto povie Androidu: "Obsah kresli az POD listami"
    WindowCompat.setDecorFitsSystemWindows(getWindow(), true);
  }
}
