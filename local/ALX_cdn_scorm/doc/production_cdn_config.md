# ALX SCORM CDN - Production Configuration

## Your Actual Setup (IP-Restricted S3 + Cloudflare Worker)

**Security Model:** S3 bucket accessible only from Cloudflare IP addresses

---

## 1. AWS S3 Bucket Configuration

### Bucket Details
- **Bucket Name:** `akt-scorm-prod`
- **Region:** `ap-south-1` (Mumbai)
- **Access:** Public with IP restriction (Cloudflare only)

### Bucket Policy (IP-Restricted)

**Path:** S3 Console → Permissions → Bucket Policy

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "AllowCloudflareOnly",
            "Effect": "Allow",
            "Principal": "*",
            "Action": "s3:GetObject",
            "Resource": "arn:aws:s3:::akt-scorm-prod/*",
            "Condition": {
                "IpAddress": {
                    "aws:SourceIp": [
                        "173.245.48.0/20",
                        "103.21.244.0/22",
                        "103.22.200.0/22",
                        "103.31.4.0/22",
                        "141.101.64.0/18",
                        "108.162.192.0/18",
                        "190.93.240.0/20",
                        "188.114.96.0/20",
                        "197.234.240.0/22",
                        "198.41.128.0/17",
                        "162.158.0.0/15",
                        "104.16.0.0/13",
                        "104.24.0.0/14",
                        "172.64.0.0/13",
                        "131.0.72.0/22"
                    ]
                }
            }
        }
    ]
}
```

**What this does:**
- ✅ Allows `GetObject` (read) access to all files
- ✅ Only from Cloudflare IP ranges
- ✅ Blocks all other IPs (including direct browser access)
- ✅ No AWS credentials needed in Worker

### CORS Configuration

**Path:** S3 Console → Permissions → CORS

```json
[
    {
        "AllowedHeaders": ["*"],
        "AllowedMethods": ["GET", "HEAD"],
        "AllowedOrigins": [
            "https://scorm.machi.cloud",
            "https://dev.akt.com"
        ],
        "ExposeHeaders": ["ETag"],
        "MaxAgeSeconds": 3600
    }
]
```

**What this does:**
- ✅ Allows GET and HEAD requests
- ✅ Only from your CDN domain and Moodle domain
- ✅ Exposes ETag header for caching
- ✅ Caches CORS preflight for 1 hour

---

## 2. Cloudflare DNS Configuration

### DNS Record

**Path:** Cloudflare Dashboard → DNS → Records

```
Type:    CNAME
Name:    scorm
Target:  dummy.cloudflare.com
Proxy:   ✅ Proxied (Orange Cloud)
TTL:     Auto
```

**Result:** `https://scorm.machi.cloud`

**What this does:**
- ✅ Routes traffic through Cloudflare's edge network
- ✅ Enables Worker to intercept requests
- ✅ Provides SSL/TLS automatically
- ✅ Enables Cloudflare caching

---

## 3. Cloudflare Worker Configuration

### Worker Details
- **Name:** `scorm-s3-bridge`
- **Route:** `scorm.machi.cloud/*`

### Worker Code (CORRECTED VERSION)

```javascript
export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    
    // ✅ Handle CORS preflight (OPTIONS request)
    if (request.method === "OPTIONS") {
      return new Response(null, {
        headers: {
          "Access-Control-Allow-Origin": request.headers.get("Origin") || "*",
          "Access-Control-Allow-Methods": "GET, HEAD, OPTIONS",
          "Access-Control-Allow-Headers": "Content-Type, Range",
          "Access-Control-Max-Age": "86400"
        }
      });
    }
    
    // 🔒 Security: Allow only /IR/ folder
    if (!url.pathname.startsWith("/IR/")) {
      return new Response("Forbidden: Only /IR/ path allowed", { 
        status: 403,
        headers: {
          "Content-Type": "text/plain"
        }
      });
    }
    
    // ✅ S3 bucket configuration
    const S3_ORIGIN = "https://akt-scorm-prod.s3.ap-south-1.amazonaws.com";
    const s3Url = S3_ORIGIN + url.pathname;
    
    // ✅ Fetch from S3 with aggressive caching
    const s3Response = await fetch(s3Url, {
      method: "GET",
      cf: {
        cacheEverything: true,
        cacheTtl: 2592000 // 30 days
      }
    });
    
    // Clone response to modify headers
    const response = new Response(s3Response.body, s3Response);
    
    // ✅ CORS: Allow specific origins only
    const origin = request.headers.get("Origin");
    const allowedOrigins = [
      "https://scorm.machi.cloud",
      "https://dev.akt.com"
    ];
    
    if (allowedOrigins.includes(origin)) {
      response.headers.set("Access-Control-Allow-Origin", origin);
      response.headers.set("Vary", "Origin");
    } else {
      // Fallback to dev domain if origin not matched
      response.headers.set("Access-Control-Allow-Origin", "https://dev.akt.com");
    }
    
    response.headers.set("Access-Control-Allow-Methods", "GET, HEAD, OPTIONS");
    response.headers.set("Access-Control-Allow-Headers", "Content-Type, Range");
    
    // ✅ Add cache headers
    response.headers.set("Cache-Control", "public, max-age=2592000"); // 30 days
    
    return response;
  }
};
```

### Key Features

1. **CORS Preflight Handling** - Responds to OPTIONS requests
2. **Path Restriction** - Only `/IR/` folder accessible
3. **Origin Validation** - Only allowed domains
4. **Aggressive Caching** - 30 days on Cloudflare edge
5. **No AWS Credentials** - S3 IP restriction handles auth

---

## 4. Cloudflare Worker Route

**Path:** Workers & Pages → Your Worker → Triggers → Routes

```
Route:   scorm.machi.cloud/*
Zone:    machi.cloud
Worker:  scorm-s3-bridge
```

**What this does:**
- ✅ All requests to `scorm.machi.cloud/*` go to worker
- ✅ Worker proxies to S3
- ✅ Cloudflare caches responses

---

## 5. Moodle Configuration

### Update Whitelist in player.php

**File:** `local/ALX_cdn_scorm/player.php`

```php
// Domain whitelist for CDN SCORM content
$whitelist = [
    'scorm.machi.cloud' => 'https://scorm.machi.cloud/IR'
];
```

### Upload SCORM Content to S3

**Folder Structure:**
```
akt-scorm-prod/
└── IR/
    ├── index_lms.html
    ├── imsmanifest.xml
    ├── story_content/
    ├── html5/
    └── mobile/
```

**Upload Command:**
```bash
aws s3 sync ./IR s3://akt-scorm-prod/IR/ --region ap-south-1
```

---

## 6. Testing & Verification

### Test 1: Direct S3 Access (Should Fail)

```bash
# Try to access S3 directly from your browser
curl https://akt-scorm-prod.s3.ap-south-1.amazonaws.com/IR/index_lms.html

# Expected: 403 Forbidden (IP not in Cloudflare range)
```

### Test 2: Cloudflare Worker Access (Should Work)

```bash
# Access via Cloudflare Worker
curl https://scorm.machi.cloud/IR/index_lms.html

# Expected: 200 OK with HTML content
```

### Test 3: CORS Preflight

```bash
curl -X OPTIONS https://scorm.machi.cloud/IR/index_lms.html \
  -H "Origin: https://dev.akt.com" \
  -H "Access-Control-Request-Method: GET" \
  -v

# Expected: 200 OK with CORS headers
```

### Test 4: Path Restriction

```bash
# Try to access non-IR path
curl https://scorm.machi.cloud/other/file.html

# Expected: 403 Forbidden
```

### Test 5: In Moodle

1. Open SCORM course in Moodle
2. Open Browser DevTools → Network tab
3. Verify:
   - ✅ Files loading from `scorm.machi.cloud`
   - ✅ Status 200 for all resources
   - ✅ No CORS errors in console
   - ✅ SCORM API working

---

## 7. Security Features

### What Makes This Secure

1. **IP Restriction**
   - S3 only accepts requests from Cloudflare IPs
   - Direct access blocked

2. **Path Restriction**
   - Worker only allows `/IR/` folder
   - Other paths return 403

3. **Origin Validation**
   - CORS restricted to specific domains
   - Prevents unauthorized embedding

4. **No Credentials in Code**
   - No AWS keys in Worker
   - IP-based authentication

5. **HTTPS Only**
   - Cloudflare enforces SSL/TLS
   - No mixed content issues

---

## 8. Performance Optimization

### Caching Strategy

**Cloudflare Edge Cache:**
- Duration: 30 days
- Location: 200+ edge locations worldwide
- Cache key: Full URL

**Browser Cache:**
- Duration: 30 days
- Header: `Cache-Control: public, max-age=2592000`

**Result:**
- First request: ~200-500ms (S3 fetch)
- Cached requests: ~20-50ms (edge cache)
- Repeat visits: ~5-10ms (browser cache)

---

## 9. Cost Estimation

### AWS S3
- **Storage:** 100 GB × $0.023/GB = $2.30/month
- **Requests:** 1M GET × $0.0004/1K = $0.40/month
- **Transfer:** Minimal (Cloudflare caches)
- **Total:** ~$3-5/month

### Cloudflare Workers
- **Plan:** Free tier (100K requests/day)
- **Overage:** $0.50/million requests
- **Total:** $0-5/month

**Grand Total:** ~$5-10/month for 10,000 students

---

## 10. Maintenance

### Update SCORM Content

```bash
# Sync updated content to S3
aws s3 sync ./IR s3://akt-scorm-prod/IR/ --region ap-south-1

# Purge Cloudflare cache (if needed)
# Cloudflare Dashboard → Caching → Purge Everything
```

### Update Cloudflare IP Ranges

Cloudflare occasionally adds new IP ranges. Update bucket policy:

**Get latest IPs:**
```bash
curl https://www.cloudflare.com/ips-v4
```

**Update S3 bucket policy with new IPs**

### Monitor Usage

**AWS CloudWatch:**
- S3 bucket metrics
- Request count
- Error rate

**Cloudflare Analytics:**
- Worker requests
- Cache hit rate
- Bandwidth usage

---

## 11. Troubleshooting

### Issue: 403 Forbidden from S3

**Cause:** Request not coming from Cloudflare IP

**Solution:**
1. Verify Worker route is active
2. Check DNS is proxied (orange cloud)
3. Verify S3 bucket policy has all Cloudflare IPs

### Issue: CORS Error in Browser

**Cause:** Origin not in allowed list

**Solution:**
1. Check Worker code has correct origins
2. Verify S3 CORS policy matches
3. Check browser console for actual origin

### Issue: 404 Not Found

**Cause:** File doesn't exist in S3

**Solution:**
```bash
# List files in S3
aws s3 ls s3://akt-scorm-prod/IR/ --recursive

# Verify file exists
```

---

## 12. Production Checklist

- [x] S3 bucket created with IP restriction
- [x] CORS policy configured
- [x] Cloudflare DNS record created (proxied)
- [x] Worker deployed with correct code
- [x] Worker route configured
- [x] SCORM content uploaded to S3
- [x] Moodle whitelist updated
- [x] Testing completed
- [x] Monitoring enabled

---

## Summary

**Your Setup:**
- ✅ S3 bucket: `akt-scorm-prod` (IP-restricted)
- ✅ CDN domain: `https://scorm.machi.cloud`
- ✅ Cloudflare Worker: Simple proxy with caching
- ✅ Security: IP + Origin + Path restrictions
- ✅ Performance: 30-day edge caching
- ✅ Cost: ~$5-10/month

**Advantages over General Setup:**
- ✅ Simpler (no AWS credentials)
- ✅ Faster (no signature overhead)
- ✅ Equally secure (IP restriction)
- ✅ Easier to maintain

**Status:** ✅ Production Ready

---

**Last Updated:** 2026-02-12  
**Configuration:** Production (IP-Restricted)
