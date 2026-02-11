# ALX Cloud SCORM (CDN) Plugin

## Description
This Moodle local plugin (`local_alx_cdn_scorm`) allows SCORM packages to be served from an external CDN while maintaining full tracking and grading integration with Moodle.

## Installation

1.  **Zip the Plugin**:
    - Navigate to `D:\projects\alx\ALX_cloud_scorm\local`.
    - **Rename** the folder `ALX_cdn_scorm` to `alx_cdn_scorm` (lowercase) if it isn't already.
    - Select the `alx_cdn_scorm` folder.
    - Right-click and **Zip** it. Name the file `alx_cdn_scorm.zip`.
    - **Verify**: The zip must contain the folder `alx_cdn_scorm` at the root.

2.  **Upload to Moodle**:
    - Go to **Site administration** > **Plugins** > **Install plugins**.
    - Drag and drop `alx_cdn_scorm.zip`.
    - Click **Install**.
    - If you see "Validation successful", continue.

## Configuration
1.  Go to **Site administration** > **Plugins** > **Local plugins** > **ALX Cloud SCORM (CDN)**.
2.  Set **Allowed CDN Domains** and **Debug Mode**.

## Usage
1.  Create a **SCORM Package** activity.
2.  Check **Enable CDN Delivery**.
3.  Enter the **CDN URL** (e.g., `https://example.com/course/imsmanifest.xml`).
4.  Save and Display.
