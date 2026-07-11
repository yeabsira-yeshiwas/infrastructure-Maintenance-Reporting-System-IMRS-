# General User Feature - Implementation Guide

## Overview

The General User feature allows users to submit maintenance requests without mandatory registration. Registration is optional and provides additional benefits like request tracking and notifications.

## Database Migration

**IMPORTANT:** Before using the General User feature, you must run the database migration:

1. Open phpMyAdmin
2. Select the `imrs_db` database
3. Go to the SQL tab
4. Copy and paste the contents of `database/migration_add_general_user.sql`
5. Click "Go"

This migration will:
- Add `General_User` to the role enum
- Allow NULL `user_id` in maintenance_requests table
- Add `submitter_email` and `submitter_name` fields for unregistered users

## Features

### Unregistered General Users

1. **Submit Requests** (`/public/submit_request.php`)
   - No registration required
   - Provide name and email
   - Requests go directly to Maintenance Manager (skip approval)
   - Status: Automatically set to "Approved"

2. **Track Requests** (`/public/track_request.php`)
   - Enter email address to view all requests submitted with that email
   - View request details and status

3. **View Request Details** (`/public/view_request.php`)
   - View full request details by request ID and email

### Registered General Users

1. **Simple Registration** (`/auth/register_general.php`)
   - Only requires: Username, Email, Password
   - Automatically links any previous requests (by email) to the new account

2. **Full Dashboard Access**
   - Login and access dashboard
   - Submit requests (automatically approved, goes to Maintenance Manager)
   - Track all requests in "My Requests"
   - Receive notifications

## Workflow

### Unregistered User Workflow:
1. User visits `/public/submit_request.php`
2. Fills form with name, email, and request details
3. Request is created with `user_id = NULL`
4. Status automatically set to "Approved"
5. Maintenance Manager receives notification
6. User can track by email at `/public/track_request.php`

### Registered User Workflow:
1. User registers at `/auth/register_general.php`
2. Any previous requests (by email) are linked to account
3. User logs in
4. Can submit requests (same as unregistered, but tracked in account)
5. Can view all requests in dashboard
6. Receives notifications

## Key Differences from Student/Staff

1. **No Approval Required**: General User requests skip Proctor/Office Head approval
2. **Direct to Manager**: Requests go straight to Maintenance Manager
3. **Optional Registration**: Can submit without account
4. **Simple Registration**: Only 3 fields (username, email, password)

## Access Control

- **Unregistered**: Can only submit requests and track by email
- **Registered**: Full access to dashboard, request tracking, notifications
- **Both**: Requests bypass approval workflow

## Files Created/Modified

### New Files:
- `database/migration_add_general_user.sql` - Database migration
- `public/submit_request.php` - Public request submission
- `public/track_request.php` - Email-based request tracking
- `public/view_request.php` - Public request viewing
- `auth/register_general.php` - Simple General User registration

### Modified Files:
- `requests/create.php` - Added General_User support, skip approval
- `requests/my_requests.php` - Added General_User access
- `includes/header.php` - Added General_User navigation
- `index.php` - Added General_User dashboard stats

## Testing

1. **Test Unregistered Submission**:
   - Visit `/public/submit_request.php`
   - Submit a request without logging in
   - Verify it goes directly to Maintenance Manager
   - Track it using the email

2. **Test Registration**:
   - Visit `/auth/register_general.php`
   - Register with same email used in step 1
   - Verify previous request is linked to account
   - Login and check dashboard

3. **Test Registered Submission**:
   - Login as General User
   - Submit a request
   - Verify it appears in "My Requests"
   - Verify it goes directly to Maintenance Manager (no approval needed)

## Notes

- General User requests always have status "Approved" when created
- The `approved_at` timestamp is set automatically
- Maintenance Managers see General User requests in their "Manage Requests" page
- Unregistered users can only track requests by email (no login required)



