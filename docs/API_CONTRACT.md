# StyleHub API Contract

## 1. Purpose

This document defines the API boundary between the StyleHub Laravel backend
and the StyleHub frontend applications.

StyleHub uses two frontend clients:

1. Administrator Web Application
2. Customer + Shop Owner Mobile Application

The applications must not mix role-specific authentication endpoints.

---

# 2. Application Architecture

## Administrator

Platform:

- Web application only

Role:

- `admin`

Authentication endpoint:

```text
POST /api/v1/admin/auth/login
