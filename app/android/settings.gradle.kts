
name: Build Android APK

on:
  workflow_dispatch:

jobs:
  build:
    runs-on: ubuntu-22.04
    steps:
      - uses: actions/checkout@v4

      - uses: subosito/flutter-action@v2
        with:
          channel: stable
          cache: true

      - uses: actions/setup-java@v4
        with:
          distribution: temurin
          java-version: "17"

      - name: Build APK
        working-directory: app
        run: |
          flutter doctor -v
          flutter pub get
          flutter build apk --release

      - name: Upload APK
        if: success()
        uses: actions/upload-artifact@v4
        with:
          name: smsgateway-apk
          path: app/build/app/outputs/flutter-apk/app-release.apk
