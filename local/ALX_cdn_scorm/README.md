# ALX High-Performance CDN SCORM Player

A high-performance Moodle plugin designed to offload SCORM content delivery to a CDN (Cloudflare/S3), slashing bandwidth costs to near-zero while providing sub-second loading speeds.

## 🚀 Key Benefits for Management
- **99% Cost Savings**: Offloads heavy video and asset traffic from Moodle EC2 to a CDN, eliminating high egress costs.
- **IOMAD Multi-Tenant Ready**: Automatically adapts to tenant domains (e.g., `c1.aktrea.net`) without manual configuration.
- **Sub-Second Loading**: Content is delivered from the edge (CDN), providing a premium, lag-free experience for students.
- **Full Tracking Integrity**: Seamlessly integrates with Moodle's gradebook and reporting plugins (ManiReports, Edwiser, etc.).

## 🛠 Features
- **Service Worker Acceleration**: Uses modern browser tech to intercept and redirect asset requests to the CDN securely.
- **Dynamic Origin Detection**: Zero-maintenance deployment for any number of IOMAD companies.
- **Smart Heartbeat Compatibility**: Hardened bypass rules ensure time-tracking and background services never fail.
- **CORS-Secured**: Content is cryptographically locked to your approved domains.

## 📦 Installation
1. Rename the folder to `alx_cdn_scorm` (all lowercase).
2. Zip the folder and upload via **Site Administration > Plugins > Install Plugins**.
3. Configure the **Allowed CDN Domains** in the plugin settings.

## 📄 Documentation
Detailed technical documentation and security roadmaps are available in the `doc/` directory:
- [Technical Walkthrough](doc/implementation_walkthrough.md)
- [Infrastructure Setup](doc/cdn_infrastructure_setup.md)
- [Security Hardening Roadmap](doc/todo/security_hardening.md)

---
*Developed for Aktrea by ALX Engineering.*
