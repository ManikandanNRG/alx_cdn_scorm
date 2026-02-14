# Security Hardening Roadmap

This document outlines the technical implementation plan for securing the ALX CDN SCORM plugin. These items are prioritized based on their impact on data privacy and content protection.

---

## 1. Strict `postMessage` Origins (Data Privacy)
**Problem**: The current bridge uses `window.postMessage(data, '*')`.
**Risk**: Any malicious script on the client machine can "listen" to the SCORM data stream.

### Implementation Plan:
1.  **Moodle Layer**: In `player.php`, pass the current domain (e.g., `https://c1.akt.net`) to the `player_embed.mustache` template.
2.  **Bridge Layer**: In `player_embed.mustache`, replace `postMessage(data, "*")` with `postMessage(data, state.domain)`.
3.  **Content Layer**: In `proxy.php`, update the injected API to also use the specific Moodle origin instead of the wildcard.

---

## 2. Proxy URL Validation (Server Security)
**Problem**: `proxy.php` validates that the URL belongs to the CDN, but it doesn't check if the student is authorized for that *specific* folder.
**Risk**: Unauthorized fetching of CDN content.

### Implementation Plan:
1.  **Strict Check**: Modify `proxy.php` to compare the requested URL against the exact `cdnurl` stored in the `local_alx_cdn_scorm` table for that `scormid`.
2.  **Path Sanitization**: Implement a strict "Base Directory" lock so that a student cannot use `../` to escape the course folder.

---

## 3. Signed URLs / Content Masking (Intellectual Property)
**Problem**: Static URLs for videos (mp4) are visible in the network tab.
**Risk**: Content theft/unauthorized downloading.

### Implementation Plan:
1.  **Cloudflare Worker**: Set up a Cloudflare Worker that requires a cryptographic "Token" (HMAC) to serve files.
2.  **Token Generation**: In `proxy.php`, use a Private Key to generate a token that expires in 60 minutes.
3.  **URL Rewriting**: Update `proxy.php` to append this token to every asset link (e.g., `video.mp4?token=xyz`).

---

## 4. API Integrity (Anti-Cheat)
**Problem**: A student can manually trigger the standard Moodle AJAX service to fake a "Passed" status.
**Risk**: Grade integrity.

### Implementation Plan:
1.  **Nonce System**: Before saving a grade, the server provides a one-time "Nonce" (token).
2.  **Signing**: The Bridge signs the score using this Nonce. The server verifies the signature before updating the `scorm_scoes_track` table.
3.  **Downgrade Guard**: (STATUS: COMPLETED) Maintain the current logic that prevents overwriting a "Passed" status with "Failed."

---

## Summary Checklist

| Step | Technical Effort | Benefit |
| :--- | :--- | :--- |
| **Step 1: Origin Lockdown** | 1 Hour | 100% Data Privacy |
| **Step 2: Proxy Lockdown** | 2 Hours | Server Hardening |
| **Step 3: Signed Assets** | 4 Hours | Content Security |
| **Step 4: API Signing** | 3 Hours | Grade Integrity |
