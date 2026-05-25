package com.ekidio.app;

import android.os.Bundle;
import androidx.core.view.WindowCompat;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
  @Override
  public void onCreate(Bundle savedInstanceState) {
    super.onCreate(savedInstanceState);

    // Vynutime, aby sa okno neprekryvalo so status barom (System Bars)
    // Toto povie Androidu: "Obsah kresli az POD listami"
    WindowCompat.setDecorFitsSystemWindows(getWindow(), true);
  }
}
