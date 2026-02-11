# Implementation Plan: External SCORM Bridge

## Overview

This implementation plan breaks down the External SCORM Bridge local plugin into discrete, incremental coding tasks. The plugin will be developed as a Moodle local plugin (local_externalscormbridge) with PHP for server-side integration and TypeScript for client-side bridge logic. The plugin hooks into mod_scorm's form and player rendering to add CDN URL support without modifying core SCORM code. Each task builds on previous tasks, with testing integrated throughout to catch errors early.

## Tasks

- [ ] 1. Set up project structure and core interfaces
  - Create Moodle local plugin directory structure (local/externalscormbridge)
  - Create PHP class files for core components (FormHookHandler, PlayerHookHandler, BridgeController, URLValidator, ConfigurationManager)
  - Create TypeScript interfaces and types for client-side components
  - Create language files for UI strings
  - Create settings page for admin configuration
  - _Requirements: 1.1, 1.2, 9.1_

- [ ] 2. Implement form hook to add CDN URL option
  - [ ] 2.1 Create form hook handler
    - Hook into mod_scorm's form rendering
    - Add "CDN URL" option to Package Type dropdown
    - Add CDN URL input field
    - _Requirements: 1.1, 1.2_
  
  - [ ] 2.2 Implement URL validation in form
    - Validate HTTPS URL format
    - Check URL accessibility (HEAD request)
    - Verify SCORM package validity (check for imsmanifest.xml)
    - Detect SCORM version from manifest
    - _Requirements: 1.2, 1.3, 7.1, 7.2, 7.5_
  
  - [ ]* 2.3 Write property test for URL validation
    - **Property 1: URL Validation Consistency**
    - **Validates: Requirements 1.2, 1.3, 7.1, 7.2, 7.5**
  
  - [ ] 2.4 Implement form save handler
    - Store CDN URL in mod_scorm's activity configuration
    - Store detected SCORM version
    - _Requirements: 1.5_

- [ ] 3. Implement player hook to inject bridge
  - [ ] 3.1 Create player hook handler
    - Hook into mod_scorm's player rendering
    - Detect if activity uses CDN URL
    - Retrieve CDN URL from activity config
    - _Requirements: 1.6, 3.1_
  
  - [ ] 3.2 Implement bridge injection
    - Inject cross-domain bridge JavaScript code
    - Initialize BridgeController
    - Create sandboxed iframe with external SCORM URL
    - _Requirements: 1.6, 3.1_

- [ ] 4. Implement SCORM runtime and API initialization
  - [ ] 4.1 Create SCORMRuntimeManager class
    - Detect SCORM version (1.2 vs 2004)
    - Initialize appropriate API object (window.API or window.API_1484_11)
    - Implement all required SCORM API methods (Initialize, Terminate, GetValue, SetValue, Commit, GetLastError, GetErrorString, GetDiagnostic)
    - _Requirements: 2.1, 2.2, 2.3, 2.4_
  
  - [ ]* 4.2 Write property test for SCORM version detection
    - **Property 2: SCORM Version Detection**
    - **Validates: Requirements 2.1, 2.2**
  
  - [ ]* 4.3 Write property test for API method execution
    - **Property 3: API Method Execution**
    - **Validates: Requirements 2.3, 2.4**

- [ ] 5. Implement cross-origin communication bridge
  - [ ] 5.1 Create BridgeController TypeScript class
    - Set up postMessage event listeners
    - Implement message serialization and deserialization
    - Route API calls to SCORMRuntimeManager
    - _Requirements: 3.2, 3.3, 3.4_
  
  - [ ] 5.2 Implement postMessage communication protocol
    - Define message format (messageId, method, params, result)
    - Implement message validation and origin checking
    - Handle response routing back to iframe
    - _Requirements: 3.2, 3.3, 3.4, 11.1_
  
  - [ ]* 5.3 Write property test for postMessage serialization round trip
    - **Property 4: PostMessage Serialization Round Trip**
    - **Validates: Requirements 3.2, 3.3, 3.4**
  
  - [ ] 5.4 Implement connection recovery logic
    - Detect communication failures
    - Implement automatic reconnection with retry logic
    - Log connection errors
    - _Requirements: 3.5, 10.2_

- [ ] 6. Implement tracking data capture and storage in mod_scorm tables
  - [ ] 6.1 Create TrackingDataHandler class
    - Capture tracking data from SetValue calls (cmi.core.score.raw, cmi.completion_status, cmi.suspend_data, etc.)
    - Store captured data in memory during session
    - Implement Commit handler to persist data to mod_scorm's mdl_scorm_scoes_track table
    - _Requirements: 4.1, 4.2_
  
  - [ ]* 6.2 Write property test for tracking data capture and persistence
    - **Property 5: Tracking Data Capture and Persistence**
    - **Validates: Requirements 4.1, 4.2**
  
  - [ ] 6.3 Implement suspend_data storage and retrieval
    - Encrypt suspend_data before storing in mod_scorm tables
    - Retrieve encrypted suspend_data on resume
    - Verify format and encoding preservation
    - _Requirements: 6.1, 6.2, 6.3, 11.2_
  
  - [ ]* 6.4 Write property test for suspend_data round trip
    - **Property 6: Suspend Data Round Trip**
    - **Validates: Requirements 6.1, 6.2, 6.3**

- [ ] 7. Ensure mod_scorm's gradebook integration works with CDN URLs
  - [ ] 7.1 Verify tracking data format compatibility
    - Ensure tracking data stored in mod_scorm format
    - Verify mod_scorm's gradebook integration reads the data correctly
    - Test score synchronization to gradebook
    - _Requirements: 5.1, 5.3_
  
  - [ ]* 7.2 Write property test for score synchronization
    - **Property 7: Score Synchronization to Gradebook**
    - **Validates: Requirements 5.1, 5.3**
  
  - [ ] 7.3 Verify completion status integration
    - Ensure completion status stored in mod_scorm format
    - Verify mod_scorm's completion integration works correctly
    - Test completion status updates
    - _Requirements: 5.2, 5.4_
  
  - [ ]* 7.4 Write property test for completion status synchronization
    - **Property 8: Completion Status Synchronization**
    - **Validates: Requirements 5.2, 5.4**

- [ ] 8. Implement multi-SCO and data model support
  - [ ] 8.1 Implement multi-SCO manifest parsing
    - Parse imsmanifest.xml to extract all SCOs
    - Support launching any SCO from the manifest
    - Track data separately for each SCO in mod_scorm tables
    - _Requirements: 8.1_
  
  - [ ]* 8.2 Write property test for multi-SCO support
    - **Property 11: Multi-SCO Support**
    - **Validates: Requirements 8.1**
  
  - [ ] 8.3 Implement data model mapping
    - Map SCORM 1.2 data to SCORM 2004 format and vice versa
    - Preserve data integrity during mapping
    - _Requirements: 8.2_
  
  - [ ]* 8.4 Write property test for data model mapping
    - **Property 12: Data Model Mapping**
    - **Validates: Requirements 8.2**
  
  - [ ] 8.5 Implement interaction and objective capture
    - Capture interaction data (id, type, result, latency, etc.)
    - Capture objective data (id, status, score, etc.)
    - Store in mod_scorm's tracking tables
    - _Requirements: 8.3, 8.4_
  
  - [ ]* 8.6 Write property test for interaction and objective capture
    - **Property 13: Interaction and Objective Capture**
    - **Validates: Requirements 8.3, 8.4**

- [ ] 9. Implement admin configuration and settings
  - [ ] 9.1 Create settings page
    - Add CDN domain whitelist configuration
    - Add timeout value configuration
    - Add debug logging toggle
    - _Requirements: 9.1, 9.2, 9.3, 9.4_
  
  - [ ] 9.2 Implement ConfigurationManager class
    - Load and validate configuration
    - Provide configuration to other components
    - _Requirements: 9.1, 9.5_
  
  - [ ]* 9.3 Write property test for CDN whitelist enforcement
    - **Property 14: CDN Whitelist Enforcement**
    - **Validates: Requirements 9.2**
  
  - [ ]* 9.4 Write property test for timeout enforcement
    - **Property 15: Timeout Enforcement**
    - **Validates: Requirements 9.4**

- [ ] 10. Implement error handling and recovery
  - [ ] 10.1 Implement URL unavailability handling
    - Detect when external URL becomes unavailable
    - Display error message to learner
    - Allow retry attempts
    - _Requirements: 10.1_
  
  - [ ]* 10.2 Write property test for URL unavailability handling
    - **Property 16: URL Unavailability Handling**
    - **Validates: Requirements 10.1**
  
  - [ ] 10.3 Implement API call timeout handling
    - Enforce configured timeout for API calls
    - Return error status on timeout
    - Log timeout events
    - _Requirements: 10.3_
  
  - [ ]* 10.4 Write property test for API call timeout handling
    - **Property 18: API Call Timeout Handling**
    - **Validates: Requirements 10.3**
  
  - [ ] 10.5 Implement session expiration handling
    - Preserve tracking data on session expiration
    - Allow resume after re-authentication
    - _Requirements: 10.5_
  
  - [ ]* 10.6 Write property test for session expiration data preservation
    - **Property 19: Session Expiration Data Preservation**
    - **Validates: Requirements 10.5**

- [ ] 11. Implement security and access control
  - [ ] 11.1 Implement postMessage origin validation
    - Validate origin of all postMessage events
    - Only process messages from expected iframe origin
    - Log security violations
    - _Requirements: 11.1, 20_
  
  - [ ]* 11.2 Write property test for postMessage origin validation
    - **Property 20: PostMessage Origin Validation**
    - **Validates: Requirements 11.1**
  
  - [ ] 11.3 Implement sensitive data encryption
    - Encrypt suspend_data before storing
    - Encrypt interaction data before storing
    - Use Moodle's encryption functions
    - _Requirements: 11.2_
  
  - [ ]* 11.4 Write property test for sensitive data encryption
    - **Property 21: Sensitive Data Encryption**
    - **Validates: Requirements 11.2**
  
  - [ ] 11.5 Verify permission checks work with mod_scorm
    - Verify learner enrollment in course
    - Verify learner has permission to access activity
    - Log unauthorized access attempts
    - _Requirements: 11.4, 11.5_
  
  - [ ]* 11.6 Write property test for access control
    - **Property 23: Access Control for Learner Activity**
    - **Validates: Requirements 11.4**

- [ ] 12. Verify mod_scorm's existing reporting works with CDN URLs
  - [ ] 12.1 Test instructor dashboard
    - Verify mod_scorm's dashboard displays CDN URL activities correctly
    - Verify learner progress is displayed
    - _Requirements: 12.1, 12.4_
  
  - [ ] 12.2 Test learner detail view
    - Verify mod_scorm's detail view shows tracking data correctly
    - Verify suspend_data and interactions are displayed
    - _Requirements: 12.2_
  
  - [ ] 12.3 Test data export
    - Verify mod_scorm's export includes CDN URL activity data
    - Verify all learner tracking data is included
    - _Requirements: 12.3_
  
  - [ ]* 12.4 Write property test for export data completeness
    - **Property 25: Export Data Completeness**
    - **Validates: Requirements 12.3**
  
  - [ ]* 12.5 Write property test for real-time dashboard updates
    - **Property 26: Real-Time Dashboard Updates**
    - **Validates: Requirements 12.4**
  
  - [ ] 12.6 Test progress reset
    - Verify mod_scorm's reset feature clears CDN URL activity data
    - Verify learner can restart activity
    - _Requirements: 12.5_
  
  - [ ]* 12.7 Write property test for progress reset completeness
    - **Property 27: Progress Reset Completeness**
    - **Validates: Requirements 12.5**

- [ ] 13. Implement logging and debugging
  - [ ] 13.1 Create logging system
    - Log all API calls (if debug enabled)
    - Log tracking data updates
    - Log errors and exceptions
    - Log security events (unauthorized access, origin validation failures)
    - _Requirements: 9.3_
  
  - [ ] 13.2 Create debug log viewer
    - Display debug logs in admin interface
    - Filter logs by type, timestamp, user
    - Export logs to file

- [ ] 14. Implement CORS handling and fallback mechanisms
  - [ ] 14.1 Implement CORS header handling
    - Check for CORS headers in response
    - Handle CORS preflight requests
    - _Requirements: 7.3_
  
  - [ ] 14.2 Implement CORS fallback mechanisms
    - If CORS fails, attempt alternative loading methods
    - Log CORS failures for debugging
    - _Requirements: 7.4_

- [ ] 15. Integration and wiring
  - [ ] 15.1 Wire all components together
    - Connect FormHookHandler to URLValidator
    - Connect PlayerHookHandler to BridgeController
    - Connect BridgeController to TrackingDataHandler
    - Connect TrackingDataHandler to mod_scorm's tracking tables
    - Connect ConfigurationManager to all components
    - _Requirements: 1.1, 1.5, 4.1, 6.1_
  
  - [ ] 15.2 Implement plugin lifecycle hooks
    - Implement lib.php with required Moodle functions
    - Implement event handlers for form and player hooks
    - _Requirements: 1.1, 1.6_
  
  - [ ]* 15.3 Write integration tests
    - Test end-to-end SCORM playback flow with CDN URL
    - Test tracking data sync to mod_scorm tables
    - Test completion status updates
    - Test session resumption
    - _Requirements: 1.6, 4.1, 6.1, 6.3_

- [ ] 16. Final checkpoint - Ensure all tests pass
  - Ensure all unit tests pass
  - Ensure all property tests pass
  - Ensure all integration tests pass
  - Verify no security vulnerabilities
  - Ask the user if questions arise

- [ ] 17. Documentation and cleanup
  - [ ] 17.1 Create README with installation and configuration instructions
  - [ ] 17.2 Create user documentation for instructors
  - [ ] 17.3 Create admin documentation for configuration
  - [ ] 17.4 Add inline code comments for complex logic
  - [ ] 17.5 Clean up temporary files and debug code

## Notes

- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation of core functionality
- Property tests validate universal correctness properties across all inputs
- Unit tests validate specific examples and edge cases
- All code should follow Moodle coding standards and best practices
- TypeScript should be compiled to JavaScript for browser compatibility
- PHP should follow PSR-12 coding standards
- Plugin reuses mod_scorm's database tables, gradebook integration, and completion tracking
- No migration needed - existing SCORM activities keep working
