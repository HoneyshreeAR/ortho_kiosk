# 📱 OrthoKiosk Mobile Application Guide

This guide explains how to use and deploy OrthoKiosk on mobile devices (Android & iOS) with **automated medicine alarms, vibration, push notifications, and SMS/WhatsApp integration**.

---

## 🌟 Option 1: Instant Mobile PWA Installation (Recommended)
You can install OrthoKiosk onto any Android phone or iPhone in 2 seconds without downloading from the Google Play Store or App Store!

### On Android (Chrome / Samsung Internet):
1. Connect your phone to the same Wi-Fi network as your computer running XAMPP.
2. Find your computer's local IP address (e.g. `192.168.1.10` via command `ipconfig`).
3. Open Chrome on your phone and visit: `http://192.168.1.10/ortho_kiosk/`
4. Tap the **"📲 Install Mobile App"** button at the top, or tap the **Chrome Menu (⋮)** -> **"Add to Home screen"** / **"Install app"**.
5. The **OrthoKiosk** app icon will appear on your phone's home screen!
6. Open it: it launches in full-screen standalone mobile mode without any browser URL bar, complete with audio alarm chimes, notification drawer alerts, and touch bottom navigation.

### On iPhone / iPad (Safari):
1. Open Safari on your iPhone and visit: `http://192.168.1.10/ortho_kiosk/`
2. Tap the **Share** button (box with an arrow pointing up).
3. Scroll down and tap **"Add to Home Screen"**.
4. Tap **Add**. The OrthoKiosk mobile icon is now on your iOS home screen!

---

## 📦 Option 2: Native Android Studio Project (.APK)
If you want to build a native Android `.apk` file or publish to Google Play Store:

1. **Open in Android Studio**:
   - Launch Android Studio.
   - Click **Open** and select the folder `c:\xampp\htdocs\ortho_kiosk\mobile_app`.
2. **Configure Host URL**:
   - Open `mobile_app/app/src/main/java/com/orthokiosk/app/MainActivity.java`.
   - Update `APP_URL`:
     - For Android Emulator: `http://10.0.2.2/ortho_kiosk/`
     - For physical phone via local Wi-Fi: `http://YOUR_LOCAL_IP/ortho_kiosk/` (e.g., `http://192.168.1.15/ortho_kiosk/`)
     - For online server: `https://your-hospital-domain.com/ortho_kiosk/`
3. **Build APK**:
   - In the top menu, select **Build** -> **Build Bundle(s) / APK(s)** -> **Build APK(s)**.
   - Once compiled, click "locate" to get your `app-debug.apk`.
   - Transfer this `.apk` to any Android phone and install!

---

## ⏰ Medicine Alarms & SMS/WhatsApp Reminders Feature Summary
- **Personalized Phone Notifications**: When medicines are scheduled, patient phone numbers are linked automatically.
- **Audio Chime & Vibration**: Loud medical alarm chime rings through the device speaker when the scheduled time arrives.
- **Background Dispatcher**: Run `cron_reminders.php` via Windows Task Scheduler to broadcast SMS and WhatsApp reminders automatically to all patients.
