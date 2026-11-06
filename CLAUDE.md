# Test Project

A web application on Laravel + PHP8 for storing PDF and DOCX files with a limited retention period.

## Technology Stack:
- Laravel + PHP8
- MySQL
- RabbitMQ
- Bootstrap + jQuery (for the frontend part)
- Docker/Docker Compose for deploy and test

## Code style:
PSR-12, strict_types, minimum external dependencies

## Functional Requirements:
1. File Upload
    - Implement an asynchronous file uploader (PDF, DOCX) via a web interface.
    - File size limit (e.g., 10MB).
    - Information about uploaded files must be stored in the database.
2. CRUD Page
    - A separate page for managing uploaded files.
    - The ability to view a list of uploaded files.
    - The ability to delete files manually.
3. Automatic File Deletion
    - Files must be automatically deleted 24 hours after upload.
    - After a file is deleted, an email notification should be sent via RabbitMQ to the address specified in .env.
    - Implement sending a message to RabbitMQ, but the actual email sending mechanism (SMTP, etc.) is not required.
    - An email notification should be sent not only upon automatic deletion after 24 hours but also upon manual deletion of a file.