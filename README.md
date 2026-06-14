# Employee Service / HRIS (Microservice)

This microservice manages the Human Resource Information System (HRIS) data, including corporate branches, job positions, and employee profiles. It also computes organizational trees and hierarchical report lines.

## Tech Stack
- **Framework:** Laravel 13
- **PHP Version:** PHP 8.3
- **Database:** MySQL 8.4 (`db_hrm`)
- **Authentication:** JWT (via middleware that validates tokens issued by the Auth Service)

---

## API Endpoints Reference

All endpoints are prefixed with `/api`.

### Public Routes
- **`GET /api/branches`**
  - Lists branches (accessible by other services, e.g. Purchasing).

### Authenticated Routes (Requires JWT Cookie)
- **`GET /api/branches/{id}`** - Retrieves details of a specific branch.
- **`GET /api/positions`** - Lists job positions.
- **`GET /api/positions/{id}`** - Retrieves details of a specific position.
- **`GET /api/employees`** - Lists employees.
- **`GET /api/employees/{id}`** - Retrieves details of a specific employee.
- **`GET /api/employees/{id}/org-tree`** - Computes and returns the organization structure for an employee.

### HRD Admin Routes (Requires JWT Cookie + admin_hrd Role)
- **Branches:**
  - `POST /api/branches` - Create branch.
  - `PUT /api/branches/{id}` - Update branch.
  - `DELETE /api/branches/{id}` - Delete branch.
- **Positions:**
  - `POST /api/positions` - Create position.
  - `PUT /api/positions/{id}` - Update position.
  - `DELETE /api/positions/{id}` - Delete position.
- **Employees:**
  - `POST /api/employees` - Register employee.
  - `PUT /api/employees/{id}` - Update employee.
  - `DELETE /api/employees/{id}` - Soft-delete/deactivate employee.

---

## Environment Configuration

A `.env.example` file is provided. Key custom variables:
- `AUTH_SERVICE_URL`: Base URL of the Auth Service (for validating user accounts).
- `JWT_ACCESS_SECRET`: Secret key for validating JWT tokens (must match Auth Service).
- `DB_DATABASE`: Defaults to `db_hrm`.
