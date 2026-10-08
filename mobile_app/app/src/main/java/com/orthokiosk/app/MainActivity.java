package com.orthokiosk.app;

import android.annotation.SuppressLint;
import android.app.Activity;
import android.content.Intent;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.view.KeyEvent;
import android.view.View;
import android.webkit.PermissionRequest;
import android.webkit.WebChromeClient;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.ProgressBar;
import android.widget.Toast;

public class MainActivity extends Activity {

    private WebView mWebView;
    private ProgressBar mProgressBar;

    // Default development server address:
    // - Use 10.0.2.2 for Android Studio Emulator connected to your PC localhost
    // - Or change to your local WiFi IP (e.g., http://192.168.1.15/ortho_kiosk/)
    // - Or change to your production domain (e.g., https://yourhospital.com/ortho_kiosk/)
    private static final String APP_URL = "http://10.0.2.2/ortho_kiosk/";

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // Request POST_NOTIFICATIONS permission for Android 13+
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            requestPermissions(new String[]{"android.permission.POST_NOTIFICATIONS"}, 101);
        }

        // Initialize WebView layout programmatically
        mWebView = new WebView(this);
        setContentView(mWebView);

        WebSettings webSettings = mWebView.getSettings();
        webSettings.setJavaScriptEnabled(true);
        webSettings.setDomStorageEnabled(true);
        webSettings.setDatabaseEnabled(true);
        webSettings.setAllowFileAccess(true);
        webSettings.setMediaPlaybackRequiresUserGesture(false); // Enable immediate alarm audio playback
        webSettings.setCacheMode(WebSettings.LOAD_DEFAULT);
        webSettings.setUseWideViewPort(true);
        webSettings.setLoadWithOverviewMode(true);

        mWebView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, String url) {
                // Open external links (e.g. WhatsApp, tel: calls, maps) in native apps
                if (url.startsWith("tel:") || url.startsWith("https://api.whatsapp.com") || url.startsWith("whatsapp:")) {
                    try {
                        Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                        startActivity(intent);
                        return true;
                    } catch (Exception e) {
                        Toast.makeText(MainActivity.this, "Cannot open external application.", Toast.LENGTH_SHORT).show();
                    }
                }
                view.loadUrl(url);
                return true;
            }
        });

        mWebView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onPermissionRequest(PermissionRequest request) {
                // Grant notification and audio permissions to the kiosk web app
                request.grant(request.getResources());
            }
        });

        // Load the OrthoKiosk mobile app
        mWebView.loadUrl(APP_URL);
    }

    @Override
    public boolean onKeyDown(int keyCode, KeyEvent event) {
        // Navigate back in web history instead of closing app
        if (event.getAction() == KeyEvent.ACTION_DOWN) {
            if (keyCode == KeyEvent.KEYCODE_BACK) {
                if (mWebView.canGoBack()) {
                    mWebView.goBack();
                    return true;
                }
            }
        }
        return super.onKeyDown(keyCode, event);
    }
}
