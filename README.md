# Leave Management System

A full-stack web application developed during my internship to manage employee leave requests with a hierarchical approval workflow.

## Project Overview
This system allows employees to submit leave requests, managers to approve or reject them, and HR/Admin users to oversee the process. Each user sees a role-based interface tailored to their permissions. The system includes leave history tracking, PDF/Excel exports, and LDAP/Active Directory authentication.

## Objectives
- Allow employees to submit leave requests.
- Implement a hierarchical validation workflow.
- Track the status of requests (Pending, Approved, Rejected).
- Generate a leave history per user.
- Manage role-based access (Employee, Manager, HR/Admin).

## Functional Modules

### 1. Authentication & Roles
- Role-based login (Employee, Manager, HR/Admin)
- Session management
- Access control according to role

### 2. User Management
- CRUD operations for users (admin/HR)
- Role assignment
- Manager ↔ Employee association

### 3. Leave Submission
- Form with start/end dates, leave type (Paid, Sick, RTT…)
- Automatic duration calculation
- Overlap validation

### 4. Approval Workflow
- Notification to manager upon new request
- Manager can approve or reject
- HR/Admin can monitor all requests and intervene if needed
- Historical record of decisions with timestamps

### 5. History & Reporting
- Leave lists per user
- Filters: date, type, status
- Remaining leave balance (optional)

## Bonus Features (Optional)
- Dynamic leave balance per user
- PDF export of requests and reports
- Email notifications at each workflow step
- Team-wide leave calendar
- Mobile-friendly / responsive design

## Tech Stack
- **Frontend:** HTML, CSS, JavaScript (optionally React/Vue)  
- **Backend:** PHP  
- **Database:** MySQL  
- **Authentication:** LDAP / Active Directory

## Usage
1. Clone the repository:  
   ```bash
   git clone https://github.com/DevWizard82/leave-management-system.git
2. Navigate into the project folder:
   ```bash
   cd leave-management-system
3. Install [Composer](https://getcomposer.org/Composer-Setup.exe)
4. Install PHP dependencies
   ```bash
   composer install
5. Create your .env file based on the template (ignored in Git) and configure:
   + Database credentials
   + LDAP/Active Directory settings
6. Start your local server (XAMPP/WAMP) and open in a browser: [http://localhost/leave-management-system](http://localhost/leave-management-system)

   
