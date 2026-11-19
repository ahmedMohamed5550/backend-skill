# File Storage Standards

## 1. General Rules

- Follow Laravel's approved file storage standards.
- Never delete or overwrite an existing file before the related database transaction successfully commits.
- Never assume that database transactions can roll back filesystem operations.
- Keep file paths and database references consistent.

## 2. File Upload

- Validate uploaded files before storing them (allowed extensions/MIME types, max size).
- **Never build the stored file name or path from user-supplied input.** The user's original filename is attacker-controlled input; using it enables path traversal (`../../`), overwriting other files or application files, header injection, and stored XSS.
- Generate the stored name with a unique random value (for example `Storage::putFile()`, `$file->hashName()`, or a UUID) so identical uploads can never overwrite each other.
- Derive the file extension from the validated content type, never blindly from `getClientOriginalName()` / `getClientOriginalExtension()`.
- Keep the user's original filename only as database metadata (for example an `original_name` column) for display purposes; never use it in the storage path or URL.
- Store files using the approved Laravel Storage system; never rely on the raw request filename for any filesystem operation.
- Store the file path in the database when required.

## 3. File Replacement

When replacing an existing file:

1. Upload and store the new file using a unique path. Never overwrite the old file.
2. Keep the old file unchanged during the database transaction.
3. Update the database record to reference the new file inside the transaction.
4. Delete the old file ONLY after the database transaction successfully commits.
5. If the transaction fails or rolls back, keep the old file and clean up the newly uploaded file when safe.
6. If deleting the old file fails after commit, log the failure clearly without treating the committed database transaction as rolled back.

## 4. File Deletion

- When deleting a database record that references a stored file, do not delete the file before the database transaction commits.
- Delete the associated file only after the related database deletion successfully commits.
- If the transaction fails, keep the original file unchanged.
- If post-commit file deletion fails, log the failure clearly.

## 5. Error Handling and Logging

- Handle file storage operations and failures explicitly.
- Log upload, replacement, and deletion failures with relevant context.
- Never report a database transaction as failed if it has already committed successfully and only the subsequent file deletion failed.
- Avoid leaving unnecessary orphaned files whenever safe cleanup is possible.

## 6. Storage Consistency

- Apply these rules to local and external storage disks, including S3.
- Never assume filesystem or external storage operations participate in database transactions.
- Preserve the old file until the new file reference is safely committed.
- Ensure that the database always references an existing valid file whenever the operation is considered successful.