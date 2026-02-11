# External SCORM Bridge Plugin - Complete Specification Document

**For Management Approval**

---

## Executive Summary

### Problem Statement

Your IOMAD Moodle instance currently experiences **$100/month in EC2 bandwidth costs** when serving SCORM packages from external CDNs (S3, R2, CloudFront). The native SCORM activity supports external URLs, but fails when the SCORM package is hosted on a different domain due to browser same-origin policy restrictions. This prevents tracking data (completion, scores) from syncing back to Moodle.

### Solution Overview

We propose developing **local_externalscormbridge**, a Moodle local plugin that extends the native SCORM activity to support external CDN-hosted SCORM packages. The plugin:

1. Adds a new "CDN URL" option to the SCORM activity's Package Type dropdown
2. Implements a cross-domain communication bridge using window.postMessage
3. Reuses all existing SCORM settings (completion conditions, grading, etc.)
4. Stores tracking data in mod_scorm's native database tables
5. Automatically syncs grades and completion to Moodle's gradebook

### Expected Outcomes

- **Bandwidth Cost Reduction:** $100/month → $3/month (~97% savings)
- **Zero Migration Effort:** Existing SCORM activities continue working
- **No Infrastructure Changes:** No DNS, ALB, or Cloudflare routing changes needed
- **Seamless Integration:** Reuses all mod_scorm features and reporting

---

## Table of Contents

1. [Business Case](#business-case)
2. [Technical Architecture](#technical-architecture)
3. [Requirements](#requirements)
4. [Design Details](#design-details)
5. [Implementation Plan](#implementation-plan)
6. [Risk Assessment](#risk-assessment)
7. [Timeline & Resources](#timeline--resources)

---

## Business Case

### Current Situation

**Architecture:**
```
Browser → ALB → EC2 (Moodle) → CloudFront → S3
                ↑
         EC2 streams SCORM to user
         AWS bills this as "Data Transfer Out" (~$0.09/GB)
```

**Monthly Costs:**
- EC2 Data Transfer Out: ~900 GB × $0.09/GB = **$100/month**
- CloudFront: ~900 GB (within free tier) = $0
- **Total: $100/month**

**Problem:**
- Native SCORM activity supports external URLs but blocks cross-domain requests
- SCORM player cannot communicate with Moodle when on different domain
- Tracking data (completion, scores) fails to sync
- Users cannot use external CDN-hosted SCORM packages

### Proposed Solution

**New Architecture:**
```
Browser → S3/CloudFront (direct)
                ↑
         SCORM served directly from CDN
         EC2 only receives tracking API calls (tiny data)
```

**Expected Monthly Costs:**
- EC2 Data Transfer Out: ~30 GB × $0.09/GB = **$3/month**
- S3 → CDN: ~90 GB = ~$8-10/month
- Cloudflare CDN: ~900 GB = $0 (free tier)
- **Total: ~$10-12/month**

**Savings: ~$88-97/month (88-97% reduction)**

### Business Benefits

| Benefit | Impact |
|---------|--------|
| **Cost Savings** | $88-97/month = $1,056-1,164/year |
| **Scalability** | No EC2 bandwidth limits; can serve unlimited SCORM content |
| **Performance** | SCORM delivered from CDN edge locations (faster for users) |
| **Flexibility** | Support any CDN (S3, R2, CloudFront, etc.) |
| **No Migration** | Existing SCORM activities keep working |
| **Maintenance** | Easier to maintain than modifying core SCORM code |

---

## Technical Architecture

### High-Level Design

```
┌─────────────────────────────────────────────────────────────────┐
│                        Moodle LMS                               │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  mod_scorm Activity View (Parent Window)                 │   │
│  │  ┌────────────────────────────────────────────────────┐  │   │
│  │  │ Form Hook (local_externalscormbridge)              │  │   │
│  │  │ - Adds "CDN URL" option to Package Type dropdown   │  │   │
│  │  │ - Validates external URL                           │  │   │
│  │  │ - Stores URL in mod_scorm config                   │  │   │
│  │  └────────────────────────────────────────────────────┘  │   │
│  │  ┌────────────────────────────────────────────────────┐  │   │
│  │  │ Player Hook (local_externalscormbridge)            │  │   │
│  │  │ - Intercepts mod_scorm player rendering            │  │   │
│  │  │ - Injects cross-domain bridge                      │  │   │
│  │  │ - Manages postMessage communication                │  │   │
│  │  └────────────────────────────────────────────────────┘  │   │
│  │  ┌────────────────────────────────────────────────────┐  │   │
│  │  │ Bridge Controller                                  │  │   │
│  │  │ - Routes API calls to SCORM Runtime Manager        │  │   │
│  │  │ - Manages session state                            │  │   │
│  │  │ - Handles connection recovery                      │  │   │
│  │  └────────────────────────────────────────────────────┘  │   │
│  │  ┌────────────────────────────────────────────────────┐  │   │
│  │  │ Sandboxed Iframe                                  │  │   │
│  │  │ ┌──────────────────────────────────────────────┐  │  │   │
│  │  │ │ SCORM Player (scorm-again)                   │  │  │   │
│  │  │ │ - Loads external SCORM package               │  │  │   │
│  │  │ │ - Executes SCORM API calls                   │  │  │   │
│  │  │ │ - Communicates via postMessage               │  │  │   │
│  │  │ └──────────────────────────────────────────────┘  │  │   │
│  │  └────────────────────────────────────────────────────┘  │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  mod_scorm Database Layer                               │   │
│  │  - Activity configuration (external URL stored)         │   │
│  │  - Tracking data (mdl_scorm_scoes_track)                │   │
│  │  - Gradebook integration (automatic via mod_scorm)      │   │
│  │  - Completion tracking (automatic via mod_scorm)        │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ HTTPS
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    External CDN                                 │
│  (S3, R2, CloudFront, etc.)                                     │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  SCORM Package                                           │   │
│  │  - imsmanifest.xml                                       │   │
│  │  - Learning objects (HTML, media, etc.)                  │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

### Key Design Principles

1. **Zero Migration** - Existing SCORM activities keep working
2. **Reuses Everything** - All mod_scorm settings, gradebook, completion tracking
3. **Safer** - Doesn't modify core SCORM code
4. **Easier to Maintain** - Survives Moodle updates
5. **Better UX** - Familiar SCORM activity with new option

### User Experience

**Instructor Workflow:**
1. Create SCORM activity (existing workflow)
2. Select "CDN URL" from Package Type dropdown (NEW)
3. Enter external SCORM URL
4. All other settings work as normal
5. Student plays SCORM from CDN
6. Tracking syncs to gradebook automatically

---

## Requirements

### Requirement 1: Extend SCORM Activity Form with CDN URL Option

**User Story:** As a course instructor, I want to add external SCORM packages from CDNs to my course using the native SCORM activity, so that I can deliver e-learning content without hosting it on Moodle infrastructure.

**Acceptance Criteria:**
- Plugin hooks into mod_scorm's form and adds "CDN URL" option to Package Type dropdown
- Instructor can enter external SCORM URL
- Plugin validates URL format and accessibility
- Invalid URLs display descriptive error messages
- Valid URLs are stored in mod_scorm's activity configuration
- Students can view and play SCORM from CDN URL

### Requirement 2: Support SCORM Versions

**User Story:** As a plugin developer, I want the bridge to support both SCORM 1.2 and SCORM 2004 standards.

**Acceptance Criteria:**
- Plugin detects SCORM 1.2 packages and initializes window.API
- Plugin detects SCORM 2004 packages and initializes window.API_1484_11
- All required SCORM API methods are implemented
- API calls return appropriate status codes (0 for success, 1 for failure)

### Requirement 3: Intercept SCORM Player Rendering and Inject Bridge

**User Story:** As a system architect, I want the plugin to hook into mod_scorm's player rendering to inject the cross-domain bridge transparently.

**Acceptance Criteria:**
- Plugin intercepts mod_scorm's player rendering via Moodle hooks
- Cross-domain bridge JavaScript is injected
- SCORM player loads in sandboxed iframe
- postMessage communication channel is established
- API calls are serialized and sent via postMessage
- Results are deserialized and returned to SCORM player
- Communication failures trigger automatic recovery

### Requirement 4: Reuse mod_scorm's Native Tracking Tables

**User Story:** As a system architect, I want the plugin to store tracking data in mod_scorm's native database tables.

**Acceptance Criteria:**
- Tracking data stored in mod_scorm's mdl_scorm_scoes_track table
- Suspend_data is encrypted and stored securely
- Tracking data format is compatible with mod_scorm
- All existing SCORM features work seamlessly

### Requirement 5: Leverage mod_scorm's Gradebook Integration

**User Story:** As a course instructor, I want grades and completion status to sync automatically to Moodle.

**Acceptance Criteria:**
- Scores sync to gradebook via mod_scorm's existing integration
- Completion status updates automatically
- Grade scale conversion works correctly
- Gradebook reflects learner progress in real-time

### Requirement 6: Preserve Suspend Data and Resume Capability

**User Story:** As a learner, I want to resume my SCORM activity from where I left off.

**Acceptance Criteria:**
- Suspend_data is stored securely
- Learners can resume from previous state
- Format and encoding are preserved
- Corrupted suspend_data allows restart

### Requirement 7: Validate External URLs and Handle CORS

**User Story:** As a system administrator, I want the plugin to validate external URLs and handle CORS restrictions.

**Acceptance Criteria:**
- Only HTTPS URLs are accepted
- URL accessibility is verified
- SCORM package validity is checked
- CORS headers are handled appropriately
- Fallback mechanisms for CORS failures

### Requirement 8: Support Multiple SCORM Versions and Formats

**User Story:** As a course instructor, I want to use SCORM packages in various formats.

**Acceptance Criteria:**
- Multi-SCO packages are supported
- Data model mapping (SCORM 1.2 ↔ 2004) works correctly
- Interactions and objectives are captured
- Custom data elements are stored

### Requirement 9: Provide Admin Configuration

**User Story:** As a system administrator, I want to configure global settings for the plugin.

**Acceptance Criteria:**
- CDN domain whitelist configuration
- Timeout value configuration
- Debug logging toggle
- Configuration validation

### Requirement 10: Handle Errors and Edge Cases

**User Story:** As a system architect, I want the plugin to handle errors gracefully.

**Acceptance Criteria:**
- URL unavailability is handled with retry capability
- Communication failures trigger automatic recovery
- API timeouts are handled correctly
- Session expiration preserves data
- Graceful degradation on failures

### Requirement 11: Ensure Security and Data Privacy

**User Story:** As a system administrator, I want the plugin to enforce security best practices.

**Acceptance Criteria:**
- postMessage communication uses origin validation
- Sensitive data is encrypted using Moodle's encryption
- Permission checks are enforced
- Unauthorized access is logged
- All security best practices are followed

### Requirement 12: Leverage mod_scorm's Existing Reporting

**User Story:** As a course instructor, I want to view learner progress using mod_scorm's existing reporting features.

**Acceptance Criteria:**
- Tracking data is stored in mod_scorm's native format
- mod_scorm's reporting features display data correctly
- Detailed tracking data is available in mod_scorm's views
- Export feature includes all learner data
- Real-time dashboard updates work correctly
- Progress reset feature works with CDN URLs

---

## Design Details

### Core Components

#### 1. Form Hook Handler
- Hooks into mod_scorm's form rendering
- Adds "CDN URL" option to Package Type dropdown
- Validates external URLs
- Stores URL in mod_scorm's activity configuration

#### 2. Player Hook Handler
- Hooks into mod_scorm's player rendering
- Detects if activity uses CDN URL
- Injects cross-domain bridge JavaScript
- Initializes bridge communication

#### 3. Bridge Controller
- Manages postMessage communication with iframe
- Routes API calls to SCORM Runtime Manager
- Maintains session state
- Handles connection recovery

#### 4. SCORM Runtime Manager
- Detects SCORM version (1.2 vs 2004)
- Initializes appropriate API object
- Implements all required SCORM API methods
- Handles API call execution

#### 5. Tracking Data Handler
- Captures tracking data from SCORM API calls
- Stores data in mod_scorm's native tables
- Ensures compatibility with mod_scorm's format
- Handles suspend_data encryption

#### 6. URL Validator
- Validates HTTPS URLs
- Checks URL accessibility
- Verifies SCORM package validity
- Enforces domain whitelist (if configured)

#### 7. Configuration Manager
- Manages plugin settings
- Validates configuration values
- Provides configuration to other components

### Data Models

**Activity Configuration (stored in mod_scorm's config):**
```
{
  externalUrl: string,        // CDN URL for SCORM package
  scormVersion: '1.2' | '2004', // Detected SCORM version
  // All other mod_scorm settings remain unchanged
}
```

**Tracking Data (stored in mod_scorm's mdl_scorm_scoes_track table):**
```
{
  // Standard mod_scorm fields
  scormid: number,
  userid: number,
  scoid: number,
  attempt: number,
  
  // SCORM 1.2 data
  cmi_core_score_raw: number,
  cmi_core_status: string,
  cmi_core_lesson_location: string,
  cmi_suspend_data: string,
  
  // SCORM 2004 data
  cmi_score_raw: number,
  cmi_completion_status: string,
  cmi_progress_measure: number,
  cmi_suspend_data: string,
  
  // Metadata
  timemodified: number
}
```

### Correctness Properties

The plugin will be validated against 27 correctness properties that ensure:

1. **URL Validation Consistency** - Valid URLs accepted, invalid rejected
2. **SCORM Version Detection** - Correct API initialization for each version
3. **API Method Execution** - Methods execute correctly with proper status codes
4. **PostMessage Serialization** - Round-trip serialization preserves data
5. **Tracking Data Capture** - Data captured and persisted correctly
6. **Suspend Data Round Trip** - Suspend data preserved with exact format
7. **Score Synchronization** - Scores sync to gradebook correctly
8. **Completion Status Synchronization** - Completion status updates correctly
9. **Gradebook Retry Logic** - Exponential backoff works correctly
10. **Tracking History Completeness** - All changes recorded
11. **Multi-SCO Support** - Multiple SCOs handled correctly
12. **Data Model Mapping** - SCORM 1.2 ↔ 2004 mapping works
13. **Interaction and Objective Capture** - All data captured
14. **CDN Whitelist Enforcement** - Whitelist enforced correctly
15. **Timeout Enforcement** - Timeouts enforced correctly
16. **URL Unavailability Handling** - Errors handled gracefully
17. **PostMessage Connection Recovery** - Connection recovery works
18. **API Call Timeout Handling** - Timeouts handled correctly
19. **Session Expiration Data Preservation** - Data preserved on expiration
20. **PostMessage Origin Validation** - Origin validation works
21. **Sensitive Data Encryption** - Data encrypted correctly
22. **Permission Verification** - Permissions checked correctly
23. **Access Control** - Access control enforced
24. **Unauthorized Access Logging** - Unauthorized access logged
25. **Export Data Completeness** - All data exported
26. **Real-Time Dashboard Updates** - Updates within 5 seconds
27. **Progress Reset Completeness** - All data cleared on reset

---

## Implementation Plan

### Phase 1: Foundation (Weeks 1-2)
- Set up project structure
- Implement form hook to add CDN URL option
- Implement URL validation
- Write property tests for URL validation

### Phase 2: Core Bridge (Weeks 3-4)
- Implement SCORM runtime and API initialization
- Implement cross-origin communication bridge
- Implement postMessage protocol
- Write property tests for API execution

### Phase 3: Tracking (Weeks 5-6)
- Implement tracking data capture and storage
- Implement suspend_data handling
- Verify mod_scorm's gradebook integration
- Write property tests for tracking

### Phase 4: Advanced Features (Weeks 7-8)
- Implement multi-SCO support
- Implement data model mapping
- Implement admin configuration
- Write property tests for advanced features

### Phase 5: Security & Error Handling (Weeks 9-10)
- Implement security and access control
- Implement error handling and recovery
- Implement logging and debugging
- Write property tests for security

### Phase 6: Integration & Testing (Weeks 11-12)
- Verify mod_scorm's reporting works
- Integration testing
- Final checkpoint
- Documentation and cleanup

### Total Timeline: 12 weeks (3 months)

### Resource Requirements

**Development Team:**
- 1 Senior PHP Developer (Moodle experience required)
- 1 TypeScript/JavaScript Developer
- 1 QA Engineer (testing and validation)

**Infrastructure:**
- Development Moodle instance
- Test SCORM packages (SCORM 1.2 and 2004)
- AWS credentials for S3 testing

**Tools:**
- Moodle development environment
- TypeScript compiler
- Property-based testing framework (fast-check)
- Git for version control

---

## Risk Assessment

### Technical Risks

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|-----------|
| CORS issues with CDN | Medium | Medium | Implement fallback mechanisms, test with multiple CDNs |
| postMessage communication failures | Low | High | Implement automatic recovery with retry logic |
| Tracking data format incompatibility | Low | High | Extensive testing with mod_scorm's native format |
| SCORM version detection issues | Low | Medium | Comprehensive testing with SCORM 1.2 and 2004 packages |
| Performance degradation | Low | Medium | Load testing and optimization |

### Mitigation Strategies

1. **Extensive Testing** - 27 correctness properties + unit tests + integration tests
2. **Gradual Rollout** - Deploy to test environment first, then staging, then production
3. **Monitoring** - Implement logging and monitoring for production issues
4. **Fallback Mechanisms** - CORS fallback, connection recovery, error handling
5. **Documentation** - Clear admin and user documentation

---

## Timeline & Resources

### Development Timeline

| Phase | Duration | Deliverables |
|-------|----------|--------------|
| Phase 1: Foundation | 2 weeks | Form hook, URL validation, property tests |
| Phase 2: Core Bridge | 2 weeks | SCORM runtime, postMessage bridge, property tests |
| Phase 3: Tracking | 2 weeks | Tracking capture, suspend_data, property tests |
| Phase 4: Advanced Features | 2 weeks | Multi-SCO, data mapping, admin config, property tests |
| Phase 5: Security & Error Handling | 2 weeks | Security, error handling, logging, property tests |
| Phase 6: Integration & Testing | 2 weeks | Integration tests, documentation, final checkpoint |
| **Total** | **12 weeks** | **Production-ready plugin** |

### Resource Allocation

**Development Team:**
- Senior PHP Developer: 100% (12 weeks)
- TypeScript/JavaScript Developer: 100% (12 weeks)
- QA Engineer: 50% (12 weeks)

**Total Effort:** ~22 person-weeks

### Cost Estimate

Assuming $100/hour developer rate:
- Senior PHP Developer: 480 hours × $100 = $48,000
- TypeScript Developer: 480 hours × $100 = $48,000
- QA Engineer: 240 hours × $100 = $24,000
- **Total Development Cost: ~$120,000**

**ROI Calculation:**
- Annual bandwidth savings: $1,056-1,164
- Development cost: $120,000
- Payback period: ~103 years

**Note:** While the direct ROI is long-term, the strategic benefits include:
- Unlimited scalability for SCORM content
- Better performance for users (CDN edge delivery)
- Flexibility to use any CDN provider
- Reduced operational burden on EC2

---

## Approval Checklist

- [ ] Business case approved
- [ ] Technical architecture approved
- [ ] Requirements approved
- [ ] Timeline and resources approved
- [ ] Risk assessment reviewed
- [ ] Budget approved
- [ ] Development team assigned
- [ ] Project kickoff scheduled

---

## Next Steps

1. **Management Review** - Review this document with stakeholders
2. **Approval** - Obtain formal approval to proceed
3. **Team Assignment** - Assign development team
4. **Environment Setup** - Set up development and test environments
5. **Project Kickoff** - Begin Phase 1 development

---

## Contact & Questions

For questions or clarifications about this specification, please contact the development team.

**Document Version:** 1.0  
**Date:** February 2026  
**Status:** Ready for Management Review

