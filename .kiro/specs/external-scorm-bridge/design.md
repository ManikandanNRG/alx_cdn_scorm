# Design Document: External SCORM Bridge

## Overview

The External SCORM Bridge is a Moodle local plugin (local_externalscormbridge) that extends the native SCORM activity module (mod_scorm) to support SCORM packages hosted on external CDNs. Instead of creating a separate activity type, the plugin hooks into mod_scorm's form rendering to add a new "CDN URL" package type option. When selected, the plugin intercepts the SCORM player rendering and injects a cross-domain communication bridge that solves the same-origin policy restriction. All existing SCORM settings (completion conditions, grading, grade scale, etc.) are reused, and tracking data is stored in mod_scorm's native database tables (mdl_scorm_scoes_track).

**Key Design Principles:**
- Zero migration effort - existing SCORM activities keep working
- Reuses all mod_scorm settings and configuration
- Safer - doesn't modify core SCORM code
- Easier to maintain - survives Moodle updates
- Better UX - familiar SCORM activity with new option
- Secure cross-origin communication using postMessage with origin validation
- Support for both SCORM 1.2 and SCORM 2004 standards
- Automatic tracking data synchronization via mod_scorm's existing integration
- Preservation of suspend_data for session resumption
- Graceful error handling with retry logic and fallback mechanisms

## Architecture

### High-Level System Architecture

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

### Integration Points with mod_scorm

**Form Hook (mod_form_hook):**
- Intercepts mod_scorm's form rendering
- Adds "CDN URL" option to Package Type dropdown
- Validates external URL before saving
- Stores URL in mod_scorm's activity configuration

**Player Hook (player_hook):**
- Intercepts mod_scorm's player rendering
- Detects if activity uses CDN URL
- Injects cross-domain bridge JavaScript
- Establishes postMessage communication

**Tracking Integration:**
- All tracking data stored in mod_scorm's native mdl_scorm_scoes_track table
- Gradebook updates handled by mod_scorm's existing integration
- Completion tracking handled by mod_scorm's existing integration
- No separate tracking tables needed

### Communication Flow

```
1. Instructor selects "CDN URL" from Package Type dropdown
   ↓
2. Form Hook validates URL and stores in mod_scorm config
   ↓
3. Student views SCORM activity
   ↓
4. Player Hook intercepts rendering and injects bridge
   ↓
5. Bridge Controller creates sandboxed iframe
   ↓
6. Iframe loads external SCORM package from CDN
   ↓
7. SCORM player initializes and calls API methods
   ↓
8. API calls are serialized and sent via postMessage to parent
   ↓
9. Bridge Controller receives postMessage, deserializes, executes API call
   ↓
10. Bridge Controller sends result back via postMessage
   ↓
11. SCORM player receives result and continues execution
   ↓
12. When tracking data is updated (SetValue, Commit), Bridge Controller:
    - Captures tracking data
    - Stores in mod_scorm's mdl_scorm_scoes_track table
    - mod_scorm's existing integration syncs to gradebook and completion
```

## Components and Interfaces

### 1. Form Hook Handler

**Responsibilities:**
- Hook into mod_scorm's form rendering
- Add "CDN URL" option to Package Type dropdown
- Validate external URLs
- Store URL in mod_scorm's activity configuration

**Key Methods:**
```
class FormHookHandler {
  // Hook into mod_scorm form
  static hookFormDefinition(mform: MoodleForm, context: any): void
  
  // Add CDN URL field to form
  static addCDNURLField(mform: MoodleForm): void
  
  // Validate CDN URL on form submission
  static validateCDNURL(url: string): ValidationResult
  
  // Store URL in mod_scorm config
  static storeCDNURL(scormId: number, url: string): Promise<void>
}
```

### 2. Player Hook Handler

**Responsibilities:**
- Hook into mod_scorm's player rendering
- Detect if activity uses CDN URL
- Inject cross-domain bridge JavaScript
- Initialize bridge communication

**Key Methods:**
```
class PlayerHookHandler {
  // Hook into mod_scorm player rendering
  static hookPlayerRendering(scormId: number, userId: number): string
  
  // Check if activity uses CDN URL
  static isCDNURLActivity(scormId: number): boolean
  
  // Get CDN URL from activity config
  static getCDNURL(scormId: number): Promise<string>
  
  // Inject bridge JavaScript
  static injectBridgeScript(cdnUrl: string): string
}
```

### 3. Bridge Controller (Parent Window)

**Responsibilities:**
- Manage postMessage communication with iframe
- Route API calls to appropriate handlers
- Maintain session state
- Handle connection recovery

**Key Methods:**
```
class BridgeController {
  constructor(activityId, userId, courseId)
  
  // Initialize bridge and iframe
  initializeBridge(externalUrl: string): Promise<void>
  
  // Handle postMessage from iframe
  handlePostMessage(event: MessageEvent): void
  
  // Send response back to iframe
  sendResponse(messageId: string, result: any): void
  
  // Handle API call from SCORM player
  handleAPICall(method: string, params: any): Promise<any>
  
  // Recover connection if interrupted
  recoverConnection(): Promise<void>
  
  // Cleanup on activity close
  cleanup(): void
}
```

### 4. SCORM Runtime Manager

**Responsibilities:**
- Detect SCORM version (1.2 vs 2004)
- Initialize appropriate API object
- Implement SCORM API methods
- Handle API call execution

**Key Methods:**
```
class SCORMRuntimeManager {
  constructor(version: '1.2' | '2004')
  
  // Initialize SCORM API
  initializeAPI(): void
  
  // Execute API method
  executeAPIMethod(method: string, params: any): Promise<any>
  
  // Get current API state
  getAPIState(): any
  
  // Validate API parameters
  validateParameters(method: string, params: any): boolean
}
```

### 5. Tracking Data Handler

**Responsibilities:**
- Capture tracking data from SCORM API calls
- Store tracking data in mod_scorm's native tables
- Ensure compatibility with mod_scorm's format

**Key Methods:**
```
class TrackingDataHandler {
  constructor(activityId: number, userId: number)
  
  // Capture tracking data from SetValue call
  captureTrackingData(dataModel: string, value: any): void
  
  // Persist tracking data to mod_scorm tables
  persistTrackingData(): Promise<void>
  
  // Retrieve suspend_data for resume
  retrieveSuspendData(): Promise<string>
  
  // Get tracking data in mod_scorm format
  getTrackingDataForModSCORM(): Promise<any>
}
```

### 6. URL Validator

**Responsibilities:**
- Validate HTTPS URLs
- Check URL accessibility
- Verify SCORM package validity
- Enforce domain whitelist (if configured)

**Key Methods:**
```
class URLValidator {
  constructor(config: PluginConfig)
  
  // Validate URL format and accessibility
  validateURL(url: string): Promise<ValidationResult>
  
  // Check if URL is in whitelist
  isWhitelisted(url: string): boolean
  
  // Verify SCORM package validity
  verifySCORMPackage(url: string): Promise<boolean>
  
  // Get SCORM version from manifest
  detectSCORMVersion(manifestUrl: string): Promise<'1.2' | '2004'>
}
```

### 7. Configuration Manager

**Responsibilities:**
- Manage plugin settings
- Validate configuration values
- Provide configuration to other components

**Key Methods:**
```
class ConfigurationManager {
  // Get plugin configuration
  getConfig(): PluginConfig
  
  // Validate configuration
  validateConfig(config: PluginConfig): ValidationResult
  
  // Save configuration
  saveConfig(config: PluginConfig): Promise<void>
  
  // Get CDN whitelist
  getCDNWhitelist(): string[]
  
  // Get timeout value
  getTimeout(): number
  
  // Is debug logging enabled
  isDebugLoggingEnabled(): boolean
}
```

## Data Models

### Activity Configuration (stored in mod_scorm's config)

```
{
  externalUrl: string,        // CDN URL for SCORM package
  scormVersion: '1.2' | '2004', // Detected SCORM version
  // All other mod_scorm settings remain unchanged
}
```

### Tracking Data (stored in mod_scorm's mdl_scorm_scoes_track table)

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

## Correctness Properties

A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.

### Property 1: URL Validation Consistency

*For any* HTTPS URL pointing to a valid SCORM package, the URL validation should accept it and allow activity creation. For any non-HTTPS URL or invalid SCORM package, validation should reject it.

**Validates: Requirements 1.2, 1.3, 7.1, 7.2, 7.5**

### Property 2: SCORM Version Detection

*For any* SCORM package (1.2 or 2004), the plugin should correctly detect the version and initialize the appropriate API object (window.API for 1.2, window.API_1484_11 for 2004).

**Validates: Requirements 2.1, 2.2**

### Property 3: API Method Execution

*For any* valid SCORM API method call with correct parameters, the plugin should execute the method and return status code 0 (success). For invalid parameters, it should return status code 1 (failure).

**Validates: Requirements 2.3, 2.4**

### Property 4: PostMessage Serialization Round Trip

*For any* SCORM API call, serializing it to a message, sending via postMessage, deserializing on the other side, and executing should produce the same result as executing the call directly.

**Validates: Requirements 3.2, 3.3, 3.4**

### Property 5: Tracking Data Capture and Persistence

*For any* SetValue call that updates tracking data, the plugin should capture the data and persist it to the database such that retrieving the data later returns the exact same value.

**Validates: Requirements 4.1, 4.2**

### Property 6: Suspend Data Round Trip

*For any* suspend_data value stored during a session, closing the session and retrieving the suspend_data on resume should return the exact same value with identical format and encoding.

**Validates: Requirements 6.1, 6.2, 6.3**

### Property 7: Score Synchronization to Gradebook

*For any* score update in the SCORM player, the plugin should synchronize the score to Moodle's gradebook API, converting it to the configured grade scale, such that querying the gradebook returns the converted score.

**Validates: Requirements 5.1, 5.3**

### Property 8: Completion Status Synchronization

*For any* completion status update in the SCORM player, the plugin should update Moodle's completion tracking system such that querying the completion status returns the updated value.

**Validates: Requirements 5.2, 5.4**

### Property 9: Gradebook Retry with Exponential Backoff

*For any* gradebook API failure, the plugin should retry the update with exponential backoff (1s, 2s, 4s, 8s) until success or maximum retries reached, such that the grade is eventually synchronized.

**Validates: Requirements 5.5**

### Property 10: Tracking History Completeness

*For any* sequence of tracking data updates, the plugin should maintain a complete history of all changes such that replaying the history produces the final state.

**Validates: Requirements 4.4**

### Property 11: Multi-SCO Support

*For any* SCORM package with multiple SCOs, the plugin should support launching any SCO from the manifest and tracking data should be captured separately for each SCO.

**Validates: Requirements 8.1**

### Property 12: Data Model Mapping

*For any* SCORM package using different data models (1.2 vs 2004), the plugin should correctly map data between formats such that equivalent data is preserved.

**Validates: Requirements 8.2**

### Property 13: Interaction and Objective Capture

*For any* SCORM package with interactions and objectives, the plugin should capture and store all interaction and objective data such that retrieving the data returns all captured elements.

**Validates: Requirements 8.3, 8.4**

### Property 14: CDN Whitelist Enforcement

*For any* configured CDN whitelist, the plugin should only allow URLs from whitelisted domains and reject all other URLs.

**Validates: Requirements 9.2**

### Property 15: Timeout Enforcement

*For any* configured timeout value, the plugin should enforce this timeout for all SCORM player sessions and return an error if the timeout is exceeded.

**Validates: Requirements 9.4**

### Property 16: URL Unavailability Handling

*For any* external SCORM URL that becomes unavailable during a session, the plugin should display an error message and allow the learner to retry.

**Validates: Requirements 10.1**

### Property 17: PostMessage Connection Recovery

*For any* interrupted postMessage communication, the plugin should automatically attempt to re-establish the connection and resume normal operation.

**Validates: Requirements 3.5, 10.2**

### Property 18: API Call Timeout Handling

*For any* SCORM API call that times out, the plugin should return an error status and log the timeout event.

**Validates: Requirements 10.3**

### Property 19: Session Expiration Data Preservation

*For any* learner session that expires, the plugin should preserve all tracking data such that the learner can resume after re-authentication and retrieve the same tracking data.

**Validates: Requirements 10.5**

### Property 20: PostMessage Origin Validation

*For any* postMessage received from the iframe, the plugin should validate the origin and only process messages from the expected iframe origin.

**Validates: Requirements 11.1**

### Property 21: Sensitive Data Encryption

*For any* sensitive tracking data (suspend_data, interactions) stored in the database, the plugin should encrypt the data using Moodle's encryption functions such that the data cannot be read without decryption.

**Validates: Requirements 11.2**

### Property 22: Permission Verification for Instructor View

*For any* instructor attempting to view learner tracking data, the plugin should verify that the instructor has permission to view that learner's data and deny access if permission is not granted.

**Validates: Requirements 11.3**

### Property 23: Access Control for Learner Activity

*For any* learner attempting to access the SCORM activity, the plugin should verify that the learner is enrolled in the course and has permission to access the activity.

**Validates: Requirements 11.4**

### Property 24: Unauthorized Access Logging

*For any* unauthorized access attempt, the plugin should log the attempt with details (user, timestamp, reason) and deny access.

**Validates: Requirements 11.5**

### Property 25: Export Data Completeness

*For any* export of activity data, the plugin should include all learner tracking data (scores, completion status, suspend_data, interactions) in the exported file.

**Validates: Requirements 12.3**

### Property 26: Real-Time Dashboard Updates

*For any* tracking data update, the instructor dashboard should reflect the update within 5 seconds.

**Validates: Requirements 12.4**

### Property 27: Progress Reset Completeness

*For any* learner progress reset, the plugin should clear all tracking data (scores, completion status, suspend_data, interactions) such that the learner can restart from the beginning.

**Validates: Requirements 12.5**

## Error Handling

### Error Categories and Responses

**URL Validation Errors:**
- Invalid URL format → Display error message, prevent activity creation
- URL not accessible → Display error message, suggest checking URL
- Invalid SCORM package → Display error message, suggest checking package format

**SCORM Runtime Errors:**
- API method not found → Return error status code 1
- Invalid parameters → Return error status code 1
- SCORM version mismatch → Log error, attempt to auto-detect version

**Communication Errors:**
- PostMessage timeout → Log error, attempt connection recovery
- Origin validation failure → Log security warning, deny message processing
- Serialization failure → Log error, return error status to SCORM player

**Tracking Errors:**
- Database write failure → Log error, queue for retry
- Gradebook API unavailable → Queue update, retry with exponential backoff
- Permission denied → Log security warning, deny operation

**Session Errors:**
- Session expired → Preserve tracking data, allow re-authentication and resume
- Learner not enrolled → Log security warning, deny access
- Instructor lacks permission → Log security warning, deny access

### Retry Logic

**Gradebook API Retries:**
- Initial retry: 1 second
- Second retry: 2 seconds
- Third retry: 4 seconds
- Fourth retry: 8 seconds
- Maximum retries: 4
- After max retries: Log error, notify instructor

**Connection Recovery:**
- Detect communication failure
- Wait 500ms
- Attempt to re-establish connection
- If successful: Resume normal operation
- If failed: Retry up to 3 times, then log error and notify user

## Testing Strategy

### Unit Testing

Unit tests verify specific examples, edge cases, and error conditions:

1. **URL Validation Tests**
   - Valid HTTPS URLs are accepted
   - Non-HTTPS URLs are rejected
   - Invalid URL formats are rejected
   - Inaccessible URLs are rejected
   - Whitelisted domains are accepted
   - Non-whitelisted domains are rejected (if whitelist configured)

2. **SCORM Version Detection Tests**
   - SCORM 1.2 packages are correctly detected
   - SCORM 2004 packages are correctly detected
   - Invalid manifests are rejected
   - Version mismatch is handled gracefully

3. **API Method Execution Tests**
   - Valid API methods return success status
   - Invalid methods return error status
   - Parameter validation works correctly
   - Error messages are descriptive

4. **Tracking Data Tests**
   - Tracking data is captured correctly
   - Suspend_data is stored and retrieved correctly
   - Tracking history is maintained
   - Data format is preserved on retrieval

5. **Permission and Access Control Tests**
   - Enrolled learners can access activities
   - Non-enrolled learners are denied access
   - Instructors can view learner data
   - Non-instructors are denied access
   - Unauthorized access is logged

6. **Error Handling Tests**
   - URL unavailability is handled gracefully
   - Communication failures trigger recovery
   - API timeouts are handled correctly
   - Session expiration preserves data
   - Corrupted suspend_data allows restart

### Property-Based Testing

Property-based tests verify universal properties across all inputs:

1. **Property 1: URL Validation Consistency**
   - Generate random HTTPS URLs
   - Verify valid URLs are accepted
   - Generate random non-HTTPS URLs
   - Verify they are rejected

2. **Property 2: SCORM Version Detection**
   - Generate random SCORM 1.2 packages
   - Verify correct API is initialized
   - Generate random SCORM 2004 packages
   - Verify correct API is initialized

3. **Property 3: API Method Execution**
   - Generate random valid API calls
   - Verify they execute and return success
   - Generate random invalid calls
   - Verify they return error status

4. **Property 4: PostMessage Serialization Round Trip**
   - Generate random API calls
   - Serialize, send, deserialize, execute
   - Verify result matches direct execution

5. **Property 5: Tracking Data Capture and Persistence**
   - Generate random tracking data
   - Capture and persist
   - Retrieve and verify exact match

6. **Property 6: Suspend Data Round Trip**
   - Generate random suspend_data
   - Store and retrieve
   - Verify exact match with identical format

7. **Property 7: Score Synchronization**
   - Generate random scores
   - Sync to gradebook
   - Query gradebook and verify

8. **Property 8: Completion Status Synchronization**
   - Generate random completion statuses
   - Update in Moodle
   - Query and verify

9. **Property 9: Gradebook Retry Logic**
   - Simulate gradebook failures
   - Verify exponential backoff is applied
   - Verify eventual success

10. **Property 10: Tracking History Completeness**
    - Generate random sequence of updates
    - Verify all changes are recorded
    - Verify replay produces final state

**Property Test Configuration:**
- Minimum 100 iterations per property test
- Each test tagged with: `Feature: external-scorm-bridge, Property N: [property_text]`
- Use fast-check (JavaScript) or Hypothesis (Python) for property generation
- Configure generators for realistic SCORM data

### Testing Coverage Goals

- Unit tests: 85%+ code coverage
- Property tests: All testable acceptance criteria covered
- Integration tests: End-to-end SCORM playback and tracking
- Security tests: CORS handling, origin validation, permission checks
- Performance tests: Timeout enforcement, connection recovery speed

