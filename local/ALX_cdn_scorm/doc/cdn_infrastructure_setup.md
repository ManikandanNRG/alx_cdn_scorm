# CDN Infrastructure Setup Guide

## Complete S3 Bucket & Cloudflare Worker Configuration

This guide provides detailed instructions for setting up the CDN infrastructure to deliver SCORM content via AWS S3 and Cloudflare Workers.

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [AWS S3 Bucket Setup](#aws-s3-bucket-setup)
3. [Cloudflare Worker Setup](#cloudflare-worker-setup)
4. [Domain Configuration](#domain-configuration)
5. [CORS Configuration](#cors-configuration)
6. [Testing & Verification](#testing--verification)
7. [Troubleshooting](#troubleshooting)

---

## Architecture Overview

### How It Works

```
┌─────────────┐
│   Student   │
└──────┬──────┘
       │
       │ 1. Opens SCORM course
       ▼
┌─────────────────┐
│  Moodle Server  │
│  (player.php)   │
└──────┬──────────┘
       │
       │ 2. Loads player with CDN URL
       ▼
┌──────────────────┐
│ Cloudflare Worker│ ◄─── 3. Proxies requests
│  (scorm.domain)  │
└──────┬───────────┘
       │
       │ 4. Fetches SCORM content
       ▼
┌──────────────────┐
│   AWS S3 Bucket  │
│ (SCORM packages) │
└──────────────────┘
```

### Benefits

- ✅ **Global CDN** - Fast content delivery worldwide
- ✅ **Reduced Server Load** - Static content served from S3
- ✅ **CORS Handling** - Cloudflare Worker manages CORS headers
- ✅ **Caching** - Cloudflare edge caching for performance
- ✅ **Security** - S3 bucket remains private

---

## AWS S3 Bucket Setup

### Step 1: Create S3 Bucket

1. **Login to AWS Console**
   - Go to https://console.aws.amazon.com/s3/
   - Click "Create bucket"

2. **Bucket Configuration**
   ```
   Bucket name: alx-scorm-content
   Region: Choose closest to your users (e.g., ap-south-1 for India)
   
   ⚠️ IMPORTANT: Keep "Block all public access" ENABLED
   (Cloudflare Worker will access via credentials)
   ```

3. **Bucket Settings**
   - Object Ownership: ACLs disabled (recommended)
   - Bucket Versioning: Disabled (unless you need version control)
   - Default encryption: Enable (SSE-S3)
   - Object Lock: Disabled

### Step 2: Configure Bucket Policy

**Option A: Private Bucket (Recommended)**

Keep bucket private and use IAM credentials in Cloudflare Worker.

**Option B: Public Read Access**

If you want S3 to serve directly (not recommended for security):

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "PublicReadGetObject",
      "Effect": "Allow",
      "Principal": "*",
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::alx-scorm-content/*"
    }
  ]
}
```

### Step 3: Configure CORS (If Direct S3 Access)

**S3 Console → Bucket → Permissions → CORS**

```json
[
  {
    "AllowedHeaders": ["*"],
    "AllowedMethods": ["GET", "HEAD"],
    "AllowedOrigins": ["*"],
    "ExposeHeaders": [
      "ETag",
      "Content-Length",
      "Content-Type"
    ],
    "MaxAgeSeconds": 3600
  }
]
```

### Step 4: Create IAM User for Cloudflare Worker

1. **Go to IAM Console**
   - https://console.aws.amazon.com/iam/

2. **Create New User**
   ```
   User name: cloudflare-worker-scorm
   Access type: Programmatic access (Access key)
   ```

3. **Attach Policy**
   
   Create custom policy:
   ```json
   {
     "Version": "2012-10-17",
     "Statement": [
       {
         "Effect": "Allow",
         "Action": [
           "s3:GetObject",
           "s3:ListBucket"
         ],
         "Resource": [
           "arn:aws:s3:::alx-scorm-content",
           "arn:aws:s3:::alx-scorm-content/*"
         ]
       }
     ]
   }
   ```

4. **Save Credentials**
   ```
   Access Key ID: AKIA...
   Secret Access Key: wJalrXUtn...
   
   ⚠️ SAVE THESE - You'll need them for Cloudflare Worker
   ```

### Step 5: Upload SCORM Content

**Folder Structure:**

```
alx-scorm-content/
├── IR/                          # Course folder (course ID or name)
│   ├── index_lms.html
│   ├── imsmanifest.xml
│   ├── story_content/
│   │   ├── audio/
│   │   ├── video/
│   │   └── images/
│   └── html5/
│       └── lib/
└── another-course/
    └── ...
```

**Upload Methods:**

**Method 1: AWS Console**
1. Go to S3 bucket
2. Click "Upload"
3. Drag & drop SCORM package folder
4. Click "Upload"

**Method 2: AWS CLI**
```bash
# Install AWS CLI
# Configure credentials
aws configure

# Upload entire SCORM package
aws s3 cp ./IR s3://alx-scorm-content/IR/ --recursive

# Sync folder (faster for updates)
aws s3 sync ./IR s3://alx-scorm-content/IR/
```

**Method 3: S3 Browser (GUI Tool)**
- Download: https://s3browser.com/
- Connect with IAM credentials
- Drag & drop folders

---

## Cloudflare Worker Setup

### Step 1: Create Cloudflare Account

1. Go to https://dash.cloudflare.com/sign-up
2. Verify email
3. Add your domain (if you have one)

### Step 2: Create Worker

1. **Go to Workers & Pages**
   - Dashboard → Workers & Pages
   - Click "Create application"
   - Select "Create Worker"

2. **Worker Configuration**
   ```
   Worker name: scorm-cdn-proxy
   ```

### Step 3: Worker Code

**Complete Worker Script:**

```javascript
// Cloudflare Worker for SCORM CDN Proxy
// Handles CORS and proxies requests to S3

// Configuration
const S3_BUCKET = 'alx-scorm-content';
const S3_REGION = 'ap-south-1';
const S3_ENDPOINT = `https://${S3_BUCKET}.s3.${S3_REGION}.amazonaws.com`;

// AWS Credentials (use Cloudflare Secrets for production)
const AWS_ACCESS_KEY_ID = 'YOUR_ACCESS_KEY_ID';
const AWS_SECRET_ACCESS_KEY = 'YOUR_SECRET_ACCESS_KEY';

// CORS headers
const CORS_HEADERS = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Methods': 'GET, HEAD, OPTIONS',
  'Access-Control-Allow-Headers': '*',
  'Access-Control-Max-Age': '86400',
};

addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request));
});

async function handleRequest(request) {
  const url = new URL(request.url);
  
  // Handle CORS preflight
  if (request.method === 'OPTIONS') {
    return new Response(null, {
      headers: CORS_HEADERS
    });
  }
  
  // Extract path (remove leading slash)
  const path = url.pathname.substring(1);
  
  // Build S3 URL
  const s3Url = `${S3_ENDPOINT}/${path}${url.search}`;
  
  try {
    // Fetch from S3
    const response = await fetch(s3Url, {
      method: request.method,
      headers: request.headers,
      cf: {
        cacheTtl: 3600,
        cacheEverything: true,
      }
    });
    
    // Clone response to modify headers
    const modifiedResponse = new Response(response.body, response);
    
    // Add CORS headers
    Object.keys(CORS_HEADERS).forEach(key => {
      modifiedResponse.headers.set(key, CORS_HEADERS[key]);
    });
    
    // Add cache headers
    modifiedResponse.headers.set('Cache-Control', 'public, max-age=3600');
    
    return modifiedResponse;
    
  } catch (error) {
    return new Response(`Error fetching from S3: ${error.message}`, {
      status: 500,
      headers: CORS_HEADERS
    });
  }
}
```

**Advanced Worker with AWS Signature V4 (For Private Buckets):**

```javascript
// Advanced Worker with AWS Signature V4 Authentication
// Use this if your S3 bucket is private

import { AwsClient } from 'aws4fetch';

const S3_BUCKET = 'alx-scorm-content';
const S3_REGION = 'ap-south-1';

const CORS_HEADERS = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Methods': 'GET, HEAD, OPTIONS',
  'Access-Control-Allow-Headers': '*',
  'Access-Control-Max-Age': '86400',
};

addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request));
});

async function handleRequest(request) {
  const url = new URL(request.url);
  
  // Handle CORS preflight
  if (request.method === 'OPTIONS') {
    return new Response(null, { headers: CORS_HEADERS });
  }
  
  // Extract path
  const path = url.pathname.substring(1);
  
  // Create AWS client
  const aws = new AwsClient({
    accessKeyId: AWS_ACCESS_KEY_ID,
    secretAccessKey: AWS_SECRET_ACCESS_KEY,
    region: S3_REGION,
    service: 's3',
  });
  
  // Build S3 URL
  const s3Url = `https://${S3_BUCKET}.s3.${S3_REGION}.amazonaws.com/${path}`;
  
  try {
    // Signed request to S3
    const signedRequest = await aws.sign(s3Url, {
      method: 'GET',
      cf: {
        cacheTtl: 3600,
        cacheEverything: true,
      }
    });
    
    const response = await fetch(signedRequest);
    
    // Add CORS headers
    const modifiedResponse = new Response(response.body, response);
    Object.keys(CORS_HEADERS).forEach(key => {
      modifiedResponse.headers.set(key, CORS_HEADERS[key]);
    });
    
    modifiedResponse.headers.set('Cache-Control', 'public, max-age=3600');
    
    return modifiedResponse;
    
  } catch (error) {
    return new Response(`Error: ${error.message}`, {
      status: 500,
      headers: CORS_HEADERS
    });
  }
}
```

### Step 4: Configure Worker Secrets (Recommended)

**Instead of hardcoding credentials, use Cloudflare Secrets:**

1. **Go to Worker Settings**
   - Workers & Pages → Your Worker → Settings

2. **Add Environment Variables**
   ```
   Variable name: AWS_ACCESS_KEY_ID
   Value: AKIA...
   
   Variable name: AWS_SECRET_ACCESS_KEY
   Value: wJalrXUtn...
   
   Variable name: S3_BUCKET
   Value: alx-scorm-content
   
   Variable name: S3_REGION
   Value: ap-south-1
   ```

3. **Update Worker Code**
   ```javascript
   // Access secrets via env
   addEventListener('fetch', event => {
     event.respondWith(handleRequest(event.request, event.env));
   });
   
   async function handleRequest(request, env) {
     const AWS_ACCESS_KEY_ID = env.AWS_ACCESS_KEY_ID;
     const AWS_SECRET_ACCESS_KEY = env.AWS_SECRET_ACCESS_KEY;
     const S3_BUCKET = env.S3_BUCKET;
     // ... rest of code
   }
   ```

### Step 5: Deploy Worker

1. **Click "Save and Deploy"**
2. **Note the Worker URL**
   ```
   https://scorm-cdn-proxy.your-account.workers.dev
   ```

---

## Domain Configuration

### Option 1: Use Worker Subdomain (Free)

**Default URL:**
```
https://scorm-cdn-proxy.your-account.workers.dev/IR/index_lms.html
```

**Pros:**
- ✅ Free
- ✅ Instant setup
- ✅ SSL included

**Cons:**
- ⚠️ Long URL
- ⚠️ Not branded

### Option 2: Custom Domain (Recommended)

**Requirements:**
- Domain managed by Cloudflare (or add to Cloudflare)

**Setup:**

1. **Add Domain to Cloudflare**
   - Dashboard → Add site
   - Follow DNS migration steps

2. **Create Worker Route**
   - Workers & Pages → Your Worker → Triggers
   - Click "Add Custom Domain"
   ```
   Custom domain: scorm.yourdomain.com
   ```

3. **DNS Configuration (Automatic)**
   - Cloudflare automatically creates DNS record
   - SSL certificate auto-provisioned

**Final URL:**
```
https://scorm.yourdomain.com/IR/index_lms.html
```

### Option 3: Subdomain with Route

**Setup:**

1. **Create DNS Record**
   - Cloudflare Dashboard → DNS
   - Add CNAME record:
   ```
   Type: CNAME
   Name: scorm
   Target: your-account.workers.dev
   Proxy status: Proxied (orange cloud)
   ```

2. **Add Worker Route**
   - Workers & Pages → Your Worker → Triggers
   - Add route:
   ```
   Route: scorm.yourdomain.com/*
   Worker: scorm-cdn-proxy
   ```

---

## CORS Configuration

### Cloudflare Worker CORS Headers

**Basic CORS (Allow All):**

```javascript
const CORS_HEADERS = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Methods': 'GET, HEAD, OPTIONS',
  'Access-Control-Allow-Headers': '*',
  'Access-Control-Max-Age': '86400',
};
```

**Restricted CORS (Specific Domain):**

```javascript
const ALLOWED_ORIGINS = [
  'https://dev.aktrea.net',
  'https://aktrea.net',
  'https://www.aktrea.net'
];

function getCorsHeaders(request) {
  const origin = request.headers.get('Origin');
  
  if (ALLOWED_ORIGINS.includes(origin)) {
    return {
      'Access-Control-Allow-Origin': origin,
      'Access-Control-Allow-Methods': 'GET, HEAD, OPTIONS',
      'Access-Control-Allow-Headers': '*',
      'Access-Control-Max-Age': '86400',
      'Vary': 'Origin',
    };
  }
  
  return {};
}
```

### S3 CORS Configuration

**If using direct S3 access (not recommended):**

```json
[
  {
    "AllowedHeaders": ["*"],
    "AllowedMethods": ["GET", "HEAD"],
    "AllowedOrigins": [
      "https://dev.aktrea.net",
      "https://aktrea.net"
    ],
    "ExposeHeaders": [
      "ETag",
      "Content-Length",
      "Content-Type",
      "Last-Modified"
    ],
    "MaxAgeSeconds": 3600
  }
]
```

---

## Testing & Verification

### Step 1: Test S3 Upload

**Verify file is accessible:**

```bash
# Public bucket
curl -I https://alx-scorm-content.s3.ap-south-1.amazonaws.com/IR/index_lms.html

# Should return 200 OK
```

### Step 2: Test Cloudflare Worker

**Test CORS preflight:**

```bash
curl -X OPTIONS https://scorm.yourdomain.com/IR/index_lms.html \
  -H "Origin: https://dev.aktrea.net" \
  -H "Access-Control-Request-Method: GET" \
  -v
```

**Expected response:**
```
< HTTP/2 200
< access-control-allow-origin: *
< access-control-allow-methods: GET, HEAD, OPTIONS
< access-control-max-age: 86400
```

**Test content fetch:**

```bash
curl https://scorm.yourdomain.com/IR/index_lms.html \
  -H "Origin: https://dev.aktrea.net" \
  -v
```

**Expected:**
- Status: 200 OK
- CORS headers present
- HTML content returned

### Step 3: Test in Moodle

1. **Update Whitelist**
   ```php
   // In player.php
   $whitelist = [
       'scorm.yourdomain.com' => 'https://scorm.yourdomain.com/IR'
   ];
   ```

2. **Open SCORM Course**
   - Navigate to course in Moodle
   - Click to launch
   - Check browser console for errors

3. **Verify in Browser DevTools**
   ```
   Network tab:
   - Files loading from scorm.yourdomain.com ✅
   - No CORS errors ✅
   - Status 200 for all resources ✅
   
   Console tab:
   - No errors ✅
   - SCORM API working ✅
   ```

---

## Troubleshooting

### Issue 1: CORS Errors

**Symptoms:**
```
Access to fetch at 'https://scorm.domain.com/...' from origin 'https://moodle.com' 
has been blocked by CORS policy
```

**Solutions:**

1. **Check Worker CORS headers**
   ```javascript
   // Ensure CORS headers are set
   'Access-Control-Allow-Origin': '*'
   ```

2. **Check preflight handling**
   ```javascript
   if (request.method === 'OPTIONS') {
     return new Response(null, { headers: CORS_HEADERS });
   }
   ```

3. **Verify origin is allowed**
   - Check ALLOWED_ORIGINS array
   - Add your Moodle domain

### Issue 2: 403 Forbidden from S3

**Symptoms:**
```
<Error>
  <Code>AccessDenied</Code>
  <Message>Access Denied</Message>
</Error>
```

**Solutions:**

1. **Check S3 bucket policy**
   - Ensure GetObject permission
   - Verify bucket name in policy

2. **Check IAM credentials**
   - Verify Access Key ID
   - Verify Secret Access Key
   - Check IAM policy permissions

3. **Check file exists**
   ```bash
   aws s3 ls s3://alx-scorm-content/IR/
   ```

### Issue 3: Worker Not Found

**Symptoms:**
```
Worker not found
```

**Solutions:**

1. **Check worker route**
   - Verify route pattern matches URL
   - Ensure worker is deployed

2. **Check DNS**
   - Verify CNAME record exists
   - Ensure proxy is enabled (orange cloud)

3. **Clear Cloudflare cache**
   - Dashboard → Caching → Purge Everything

### Issue 4: Slow Loading

**Solutions:**

1. **Enable caching in worker**
   ```javascript
   cf: {
     cacheTtl: 3600,
     cacheEverything: true,
   }
   ```

2. **Set cache headers**
   ```javascript
   'Cache-Control': 'public, max-age=3600'
   ```

3. **Use S3 Transfer Acceleration**
   - S3 Console → Properties → Transfer Acceleration
   - Update endpoint to use accelerate

4. **Optimize SCORM package**
   - Compress images
   - Minify JavaScript/CSS
   - Use appropriate video codecs

---

## Production Checklist

### Security

- [ ] S3 bucket is private (Block public access enabled)
- [ ] IAM user has minimal permissions (GetObject only)
- [ ] AWS credentials stored in Cloudflare Secrets (not hardcoded)
- [ ] CORS restricted to specific origins (not *)
- [ ] SSL/TLS enabled (automatic with Cloudflare)

### Performance

- [ ] Cloudflare caching enabled
- [ ] Cache-Control headers set
- [ ] S3 Transfer Acceleration enabled (optional)
- [ ] SCORM content optimized (compressed)

### Monitoring

- [ ] Cloudflare Analytics enabled
- [ ] S3 CloudWatch metrics enabled
- [ ] Error logging configured
- [ ] Alerts set up for failures

### Documentation

- [ ] CDN URLs documented in whitelist
- [ ] AWS credentials stored securely
- [ ] Deployment process documented
- [ ] Rollback plan prepared

---

## Cost Estimation

### AWS S3

**Storage:**
- First 50 TB: $0.023 per GB/month
- Example: 100 GB = $2.30/month

**Data Transfer:**
- First 10 TB: $0.09 per GB
- Example: 1 TB/month = $90/month

**Requests:**
- GET: $0.0004 per 1,000 requests
- Example: 1M requests = $0.40/month

### Cloudflare Workers

**Free Tier:**
- 100,000 requests/day
- 10ms CPU time per request

**Paid Plan ($5/month):**
- 10 million requests/month included
- $0.50 per additional million

**Typical Cost for 10,000 students:**
- Cloudflare: $5-10/month
- AWS S3: $50-100/month
- **Total: ~$60-110/month**

---

## Next Steps

1. ✅ Create S3 bucket
2. ✅ Upload SCORM content
3. ✅ Create Cloudflare Worker
4. ✅ Configure custom domain
5. ✅ Update Moodle whitelist
6. ✅ Test thoroughly
7. ✅ Monitor performance
8. ✅ Optimize as needed

---

**Last Updated:** 2026-02-12  
**Version:** 1.0  
**Status:** Production Ready
