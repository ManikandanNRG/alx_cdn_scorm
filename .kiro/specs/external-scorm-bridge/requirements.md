# Requirements Document: External SCORM Bridge

## Introduction

The External SCORM Bridge is a Moodle local plugin (local_externalscormbridge) that extends the native SCORM activity module (mod_scorm) to support SCORM packages hosted on external CDNs (S3, R2, CloudFront, etc.). Instead of creating a separate activity type, the plugin hooks into mod_scorm's form rendering to add a new "CDN URL" package type option. When selected, the plugin intercepts the SCORM player rendering and injects a cross-domain communication bridge that solves the same-origin policy restriction. All existing SCORM settings (completion conditions, grading, grade scale, etc.) are reused, and tracking data is stored in mod_scorm's native database tables.

## Glossary

- **Local Plugin**: A Moodle plugin type (local_*) that extends core functionality without creating new activity types
- **mod_scorm**: Moodle's native SCORM activity module that handles SCORM package management and tracking
- **Package Type**: A dropdown option in mod_scorm's form that specifies how the SCORM package is provided (Uploaded package, External SCORM manifest, or CDN URL)
- **CDN URL**: An HTTPS URL pointing to a SCORM package hosted on a CDN or external server (e.g., S3, R2, CloudFront)
- **Bridge**: The plugin component that acts as an intermediary between the external SCORM player and Moodle's tracking system
- **Tracking Data**: SCORM runtime data including completion status, score, suspend_data, and interaction records (stored in mod_scorm's native tables)
- **Cross-Origin Communication**: Communication between documents from different origins using window.postMessage API
- **Sandboxed Iframe**: An iframe element with restricted permissions that isolates the SCORM player from the main Moodle page
- **SCORM Runtime**: The JavaScript engine that executes SCORM API calls (scorm-again library)
- **Moodle API**: Moodle's web service endpoints for updating grades, completion status, and activity data
- **SCORM 1.2**: SCORM specification version 1.2 (uses window.API object)
- **SCORM 2004**: SCORM specification version 2004 (uses window.API_1484_11 object)
- **Suspend Data**: SCORM data that persists between sessions, allowing learners to resume from where they left off
- **Completion Status**: SCORM tracking state indicating whether a learner has completed the activity (completed, incomplete, not attempted)
- **Score**: Numeric grade value captured from SCORM tracking data (0-100 or custom scale)
- **Form Hook**: A Moodle hook that allows plugins to modify form elements in other modules
- **Player Hook**: A Moodle hook that allows plugins to intercept and modify the SCORM player rendering

## Requirements

### Requirement 1: Extend SCORM Activity Form with CDN URL Option

**User Story:** As a course instructor, I want to add external SCORM packages from CDNs to my course using the native SCORM activity, so that I can deliver e-learning content without hosting it on Moodle infrastructure or creating a separate activity type.

#### Acceptance Criteria

1. WHEN an instructor creates or edits a SCORM activity, THE Plugin SHALL hook into mod_scorm's form and add a new "CDN URL" option to the Package Type dropdown
2. WHEN an instructor selects "CDN URL" from the Package Type dropdown, THE Plugin SHALL display a field for entering an external SCORM URL
3. WHEN an instructor enters a valid HTTPS URL pointing to an imsmanifest.xml file, THE Plugin SHALL validate the URL format and accessibility
4. IF the URL is invalid or inaccessible, THEN THE Plugin SHALL display a descriptive error message and prevent activity save
5. WHEN an instructor saves the activity with a valid external URL, THE Plugin SHALL store the URL in mod_scorm's activity configuration
6. WHEN a student views the SCORM activity with a CDN URL, THE Plugin SHALL load the external SCORM package from the provided URL without requiring DNS or infrastructure changes

### Requirement 2: Support SCORM Versions

**User Story:** As a plugin developer, I want the bridge to support both SCORM 1.2 and SCORM 2004 standards, so that it works with diverse SCORM packages.

#### Acceptance Criteria

1. WHEN a SCORM package uses SCORM 1.2 API (window.API), THE Plugin SHALL detect and initialize the SCORM 1.2 runtime
2. WHEN a SCORM package uses SCORM 2004 API (window.API_1484_11), THE Plugin SHALL detect and initialize the SCORM 2004 runtime
3. WHEN the SCORM package initializes, THE Plugin SHALL provide the appropriate API object with all required methods (Initialize, Terminate, GetValue, SetValue, Commit, GetLastError, GetErrorString, GetDiagnostic)
4. WHEN a SCORM package calls API methods, THE Plugin SHALL execute them and return appropriate status codes (0 for success, 1 for failure)

### Requirement 3: Intercept SCORM Player Rendering and Inject Bridge

**User Story:** As a system architect, I want the plugin to hook into mod_scorm's player rendering, so that the cross-domain bridge is injected transparently without modifying core SCORM code.

#### Acceptance Criteria

1. WHEN mod_scorm renders the SCORM player for an activity with a CDN URL, THE Plugin SHALL intercept the rendering via a Moodle hook
2. WHEN the player is intercepted, THE Plugin SHALL inject the cross-domain bridge JavaScript code
3. WHEN the SCORM player loads in a sandboxed iframe, THE Plugin SHALL establish a postMessage communication channel between the iframe and the parent Moodle page
4. WHEN the SCORM player calls an API method, THE Plugin SHALL serialize the call and send it via postMessage to the parent window
5. WHEN the parent window receives an API call message, THE Plugin SHALL deserialize it, execute the call, and send the result back via postMessage
6. WHEN the iframe receives a response message, THE Plugin SHALL deserialize it and return the result to the SCORM player
7. IF a postMessage communication fails, THEN THE Plugin SHALL log the error and attempt to recover the connection

### Requirement 4: Reuse mod_scorm's Native Tracking Tables

**User Story:** As a system architect, I want the plugin to store tracking data in mod_scorm's native database tables, so that all existing SCORM features (gradebook integration, completion tracking, reporting) work seamlessly.

#### Acceptance Criteria

1. WHEN a SCORM player calls SetValue to update tracking data (cmi.core.score.raw, cmi.completion_status, cmi.suspend_data), THE Plugin SHALL capture and store this data in mod_scorm's native mdl_scorm_scoes_track table
2. WHEN a SCORM player calls Commit, THE Plugin SHALL persist all captured tracking data to mod_scorm's database tables
3. WHEN a learner resumes a SCORM activity, THE Plugin SHALL retrieve the previously stored suspend_data from mod_scorm's tables and provide it to the SCORM player
4. WHEN tracking data is updated, THE Plugin SHALL maintain compatibility with mod_scorm's existing tracking format and structure
5. IF a tracking data update fails, THEN THE Plugin SHALL log the error and notify the instructor

### Requirement 5: Leverage mod_scorm's Gradebook Integration

**User Story:** As a course instructor, I want grades and completion status to sync automatically to Moodle using mod_scorm's existing integration, so that the gradebook reflects learner progress without additional configuration.

#### Acceptance Criteria

1. WHEN a SCORM player updates the score (cmi.core.score.raw or cmi.score.raw), THE Plugin SHALL store the score in mod_scorm's tracking tables, which automatically syncs to Moodle's gradebook via mod_scorm's existing integration
2. WHEN a SCORM player updates the completion status (cmi.core.status or cmi.completion_status), THE Plugin SHALL update the tracking data in mod_scorm's tables, which automatically updates the activity completion status in Moodle via mod_scorm's existing integration
3. WHEN the score is updated, THE Plugin SHALL ensure the score is stored in the format expected by mod_scorm (0-100 or custom scale as configured in the activity)
4. WHEN completion status is set to "completed", THE Plugin SHALL mark the activity as complete in mod_scorm's tracking, which automatically updates Moodle's completion tracking system
5. IF a tracking data update fails, THEN THE Plugin SHALL log the error and notify the instructor

### Requirement 6: Preserve Suspend Data and Resume Capability

**User Story:** As a learner, I want to resume my SCORM activity from where I left off, so that I don't lose progress between sessions.

#### Acceptance Criteria

1. WHEN a SCORM player calls SetValue to update suspend_data (cmi.suspend_data or cmi.core.lesson_location), THE Plugin SHALL store this data securely
2. WHEN a learner closes the SCORM player and returns later, THE Plugin SHALL retrieve the stored suspend_data and provide it to the SCORM player
3. WHEN suspend_data is retrieved, THE Plugin SHALL ensure it matches the exact format and encoding used when it was stored
4. WHEN a learner resumes, THE Plugin SHALL restore the SCORM player to the exact state it was in before closing (including bookmarks, progress, and interactions)
5. IF suspend_data is corrupted or missing, THEN THE Plugin SHALL allow the learner to restart the activity from the beginning

### Requirement 7: Validate External URLs and Handle CORS

**User Story:** As a system administrator, I want the plugin to validate external URLs and handle CORS restrictions, so that only legitimate SCORM packages are loaded.

#### Acceptance Criteria

1. WHEN an instructor enters an external URL, THE Plugin SHALL validate that it is a valid HTTPS URL
2. WHEN an instructor enters an external URL, THE Plugin SHALL verify that the URL is accessible and returns a valid SCORM package (imsmanifest.xml)
3. WHEN the SCORM package is loaded in an iframe, THE Plugin SHALL handle CORS headers appropriately (Access-Control-Allow-Origin, etc.)
4. IF the external server does not support CORS, THEN THE Plugin SHALL attempt to load the content using alternative methods (e.g., proxy if configured)
5. IF the URL is not HTTPS, THEN THE Plugin SHALL reject it and display an error message

### Requirement 8: Support Multiple SCORM Versions and Formats

**User Story:** As a course instructor, I want to use SCORM packages in various formats, so that I have flexibility in content sourcing.

#### Acceptance Criteria

1. WHEN a SCORM package contains multiple SCOs (Shareable Content Objects), THE Plugin SHALL support launching any SCO from the manifest
2. WHEN a SCORM package uses different data models (SCORM 1.2 vs 2004), THE Plugin SHALL correctly map data between formats
3. WHEN a SCORM package includes interactions and objectives, THE Plugin SHALL capture and store these data elements
4. WHEN a SCORM package uses custom data elements, THE Plugin SHALL store them in a flexible data structure for future retrieval

### Requirement 9: Provide Admin Configuration

**User Story:** As a system administrator, I want to configure global settings for the plugin, so that I can control behavior across the Moodle instance.

#### Acceptance Criteria

1. WHEN an administrator accesses the plugin settings, THE Plugin SHALL display configuration options for CDN settings, timeout values, and logging levels
2. WHEN an administrator configures a default CDN domain whitelist, THE Plugin SHALL only allow URLs from whitelisted domains when instructors create activities
3. WHEN an administrator enables debug logging, THE Plugin SHALL log all API calls and tracking data updates to a debug log file
4. WHEN an administrator configures a timeout value, THE Plugin SHALL enforce this timeout for all SCORM player sessions
5. WHEN an administrator saves settings, THE Plugin SHALL validate all configuration values and display confirmation messages

### Requirement 10: Handle Errors and Edge Cases

**User Story:** As a system architect, I want the plugin to handle errors gracefully, so that failures don't disrupt the learning experience.

#### Acceptance Criteria

1. IF the external SCORM URL becomes unavailable during a session, THEN THE Plugin SHALL display an error message and allow the learner to retry
2. IF the postMessage communication is interrupted, THEN THE Plugin SHALL attempt to re-establish the connection automatically
3. IF a SCORM API call times out, THEN THE Plugin SHALL return an error status and log the timeout event
4. IF the Moodle gradebook API is unavailable, THEN THE Plugin SHALL queue the grade update and retry when the API becomes available
5. IF a learner's session expires, THEN THE Plugin SHALL preserve all tracking data and allow the learner to resume after re-authentication

### Requirement 11: Ensure Security and Data Privacy

**User Story:** As a system administrator, I want the plugin to enforce security best practices, so that learner data is protected.

#### Acceptance Criteria

1. WHEN tracking data is transmitted between the iframe and parent window, THE Plugin SHALL use secure postMessage communication with origin validation
2. WHEN tracking data is stored in the database, THE Plugin SHALL encrypt sensitive data (suspend_data, interactions) using Moodle's encryption functions
3. WHEN an instructor views tracking data, THE Plugin SHALL verify that the instructor has permission to view that learner's data
4. WHEN a learner accesses the SCORM activity, THE Plugin SHALL verify that the learner is enrolled in the course and has permission to access the activity
5. IF an unauthorized access attempt is detected, THEN THE Plugin SHALL log the attempt and deny access

### Requirement 12: Leverage mod_scorm's Existing Reporting

**User Story:** As a course instructor, I want to view learner progress and tracking data using mod_scorm's existing reporting features, so that I can monitor engagement without learning new interfaces.

#### Acceptance Criteria

1. WHEN an instructor views the SCORM activity, THE Plugin SHALL ensure all tracking data is stored in mod_scorm's native format so that mod_scorm's existing reporting features display the data correctly
2. WHEN an instructor clicks on a learner's name in mod_scorm's reporting interface, THE Plugin SHALL ensure detailed tracking data (suspend_data, interactions, completion history) is available through mod_scorm's existing views
3. WHEN an instructor exports activity data using mod_scorm's export feature, THE Plugin SHALL ensure all learner tracking data is included in the export
4. WHEN an instructor views the mod_scorm gradebook, THE Plugin SHALL ensure real-time updates of learner progress are reflected (updated within 5 seconds of SCORM player updates)
5. WHEN an instructor needs to reset a learner's progress using mod_scorm's reset feature, THE Plugin SHALL ensure all tracking data is cleared and the learner can restart the activity

