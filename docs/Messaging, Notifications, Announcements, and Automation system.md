Implement a complete **Messaging, Notifications, Announcements, and Automation system** for the LMS.

Design it like a real production LMS used by **students, instructors, and administrators**, with a clear communication hierarchy, role-based permissions, automatic notifications, event-driven workflows, and proper integration with the existing learning/progress system.

The system should not depend on instructors or administrators manually creating notifications for events that the LMS can reliably detect automatically.

---

# 1. Communication Hierarchy

Create clear communication boundaries between the LMS roles.

### Student ↔ Instructor

* Students can message instructors for courses/modules they are enrolled in.
* When a student enrolls in a course/module, automatically make the appropriate course communication/chat available.
* Instructors can communicate with enrolled students.
* Keep conversations scoped to the relevant course/module where appropriate.
* Students should not be able to access conversations belonging to other students or unrelated courses.

### Student/Instructor ↔ Admin

Users should have a way to report:

* System problems
* Account issues
* Course/system issues
* Technical problems
* Other platform-related concerns

Admins can reply to those reports.

Treat these as trackable conversations/support threads so the user and administrator can see the complete history and status.

Users must only see conversations they are authorized to access.

---

# 2. Activity Notifications

Create meaningful **automatic notifications** for LMS activities.

### Student → Instructor

Examples:

* Student starts a quiz.
* Student submits/completes a quiz.
* Student submits an assignment.
* Student completes a lesson/module.
* Student becomes eligible for certification.

### Instructor → Student

Examples:

* Quiz/assignment has been graded.
* Student passes an assessment.
* Student fails an assessment.
* Student completes a lesson/module.
* Certificate becomes available.
* Student needs to retake an assignment/quiz because the required passing criteria were not met.
* Instructor publishes new learning content.

Do not create unnecessary notification spam.

Notifications should have:

* Clear title
* Description
* Timestamp
* Read/unread state
* Notification type/icon
* Relevant course/module
* Link to the relevant page where appropriate

---

# 3. Instructor Content Upload / Publishing Notifications

When an instructor **publishes or uploads new learning content** to a course/module, automatically notify the students who are **currently enrolled and authorized to access that course/module**.

This applies to relevant content such as:

* New lessons
* Learning materials
* Documents/files
* Videos
* Quizzes
* Assignments
* Other published course content

Example workflow:

```text id="x7o9v1"
Instructor publishes new content
        ↓
System identifies currently enrolled students
        ↓
System generates appropriate notifications
        ↓
Students see the notification in their dashboard/notification center
        ↓
Student can open the notification
        ↓
System takes the student directly to the new content
```

The notification should clearly indicate:

* Instructor
* Course/module
* What content was added
* Date/time
* Direct link to the content

Only notify students who are **currently enrolled and authorized to access the course/module**.

Do not notify:

* Unenrolled users
* Students who have been removed from the course
* Students whose access has expired
* Unauthorized users

Avoid duplicate notifications when the instructor simply edits existing content.

Distinguish between:

* New content published
* Existing content edited
* Content unpublished

Only generate notifications when the change is meaningful to the student.

This must be handled automatically by the server-side LMS event/business logic rather than requiring the instructor to manually notify students.

---

# 4. Learning Completion & Certification

Connect notifications and automation to the **actual LMS learning logic**.

When a student completes lessons, assignments, quizzes, and other required activities:

* Determine whether the student satisfies the configured completion requirements.
* Determine whether required assessments were passed.
* Determine whether the student is eligible for certification.
* If requirements are satisfied, notify the student that they are eligible for the certificate.
* If requirements are not satisfied, clearly explain what remains incomplete or what needs to be retaken.
* Do not mark a student as certified simply because they opened a lesson.
* Completion and certification must be determined server-side from the actual requirements.

The system should automatically recalculate completion and certificate eligibility when relevant grades or activity states change.

---

# 5. Announcements

Add a proper **Announcements** system.

### Instructor Announcements

Instructors can create announcements for students enrolled in their courses/modules.

Students should be able to see:

* Announcement
* Instructor
* Course/module
* Date/time
* Read/unread state

Creating an announcement should automatically generate the appropriate student notifications.

### Admin Announcements

Admins can create platform-wide announcements.

Examples:

* Scheduled maintenance
* System updates
* Important notices
* Downtime
* Platform changes
* Policy announcements
* General LMS announcements

These should appear on the appropriate user dashboards and notification center.

Do not expose instructor/course announcements to users who are not enrolled or authorized to see them.

---

# 6. Notification Center

Create a unified notification center accessible from the dashboard/navigation.

Include:

* Unread count/badge
* Read/unread state
* Timestamp
* Notification type/icon
* Relevant course/module
* Click-through to the related page
* Mark as read
* Mark all as read
* Sensible grouping where appropriate

Keep the UI clean and consistent with the existing flat LMS design.

Notifications should be useful rather than overwhelming.

---

# 7. Messaging UI

Create a proper LMS messaging experience with:

* Conversation list
* Unread messages
* Message history
* Timestamps
* Message composer
* Sender/recipient identity
* Course/module context when applicable
* Responsive mobile layout
* Appropriate empty/loading/error states

Do not make it look like a generic social-media messenger.

It should feel like an **educational LMS communication system**.

---

# 8. Automatic Course Communication

When a student enrolls in a course/module:

```text id="4n2q1z"
Student enrolls
    ↓
Course communication becomes available
    ↓
Student can communicate with the appropriate instructor
```

Do not require the instructor to manually create a conversation for every enrolled student.

However, do not automatically create unnecessary empty message records if the architecture can instead establish the communication relationship when the first message is sent.

Use the most appropriate approach for the existing database architecture.

---

# 9. Role-Based Permissions

Enforce all communication permissions **server-side**.

### Students must not:

* Message unrelated instructors through course communication.
* Access another student's conversations.
* Access admin-only reports.
* Access unrelated course announcements.
* Modify notification records belonging to another user.
* Access course content after their authorization/enrollment has ended.

### Instructors must not:

* Access unrelated course conversations.
* Access administrator conversations unless explicitly involved.
* Modify another instructor's course communication.
* Send course announcements to students outside their authorized courses.
* Send course-content notifications to students who are not enrolled.

### Admins:

* Can manage/respond to legitimate support/system reports.
* Can publish global announcements.
* Can access administrative communication according to the existing role permissions.

Do not rely on frontend visibility or hidden buttons for security.

---

# 10. Automation & Event-Driven Logic

Extend the communication system with **automatic LMS workflows**.

Do not make instructors or administrators manually create notifications for events that the LMS can reliably detect.

Use existing LMS data and business logic as the source of truth.

Automations should trigger from actual events such as:

* Student enrolls in a course/module
* Student opens/starts a lesson
* Student completes a lesson
* Student starts a quiz
* Student submits a quiz
* Student passes a quiz
* Student fails a quiz
* Student submits an assignment
* Assignment is graded
* Student passes a required assessment
* Student fails a required assessment
* Student completes all required lessons
* Student completes all required assessments
* Student becomes eligible for a certificate
* Student becomes ineligible due to a failed requirement
* Certificate is issued
* Instructor publishes new content
* Instructor updates or publishes an important course resource
* Instructor publishes an announcement
* Admin publishes an announcement
* New message is received
* Support/report conversation receives a reply

---

# 11. Automated Grading & Completion

Where the assessment type supports automatic grading, calculate the result automatically according to the configured passing requirements.

For example:

```text id="2v1m3e"
Quiz submitted
    ↓
Automatically grade
    ↓
Determine pass/fail
    ↓
Update student progress
    ↓
Check module/course requirements
    ↓
Determine completion status
    ↓
Determine certificate eligibility
    ↓
Trigger appropriate notifications
```

If the student fails:

* Record the result.
* Notify the student.
* Clearly identify that the required assessment was not passed.
* Identify what needs to be retaken or completed.
* Allow/recommend retaking according to the configured LMS rules.

If the student passes:

* Update the appropriate completion/progress state.
* Check whether all required lessons, quizzes, assignments, and other requirements are completed.
* If all requirements are satisfied, automatically mark the appropriate module/course as completed.
* Automatically determine certificate eligibility.
* Notify the student when the certificate becomes available.

Do not hardcode arbitrary passing requirements.

Use the actual course/module configuration.

---

# 12. Instructor Grading Automation

Do not automate decisions that genuinely require instructor judgment.

For manually graded assignments:

```text id="5b8xk4"
Student submits assignment
    ↓
Notify instructor
    ↓
Instructor grades assignment
    ↓
System records grade
    ↓
System determines pass/fail
    ↓
System updates progress
    ↓
System checks completion requirements
    ↓
System checks certificate eligibility
    ↓
System sends appropriate notification
```

The instructor should only perform the action that actually requires human judgment.

The system should automatically handle:

* Grade state changes
* Pass/fail determination
* Progress updates
* Completion calculations
* Certificate eligibility
* Notifications
* Relevant dashboard updates

---

# 13. Event-Driven Notifications

Notifications should be generated automatically from LMS events rather than manually created.

For example:

```text id="5xv5jc"
Student submits quiz
→ Instructor receives notification

Instructor grades assignment
→ Student receives result notification

Instructor publishes new lesson
→ Currently enrolled students receive notification

Student passes final requirement
→ System checks completion

All requirements satisfied
→ Certificate eligibility is automatically determined

Certificate available
→ Student receives notification
```

Do not duplicate notifications when the same event is processed more than once.

Use appropriate idempotency/unique event handling so retries, refreshes, duplicate submissions, or repeated requests do not create duplicate notifications.

---

# 14. Automation Architecture

Keep automation rules centralized and maintainable.

Do not scatter notification logic throughout unrelated UI components.

Prefer a clear event/service-based architecture where appropriate:

```text id="7r5g4x"
LMS Event
    ↓
Business Logic
    ↓
Progress / Grade / Completion Update
    ↓
Automation Rule
    ↓
Notification / Message / Certificate Action
```

The exact implementation should follow the project's existing architecture.

Do not introduce unnecessary infrastructure if the existing application can handle these workflows cleanly.

The database and server-side LMS rules must remain the **source of truth**.

Do not determine grades, completion, or certificate eligibility solely from frontend state.

---

# 15. Dashboard Integration

Integrate communication into the existing dashboards.

### Student Dashboard

Show relevant:

* Notifications
* Announcements
* Messages
* Course activity
* New course content notifications
* Assessment results
* Completion updates
* Certificate eligibility
* Certificate availability
* Required retakes/actions

### Instructor Dashboard

Show relevant:

* Student activity notifications
* Quiz/assignment submissions
* Items requiring grading
* New student enrollments where appropriate
* Course announcements
* Messages
* Student completion activity
* Relevant course communication

### Admin Dashboard

Show relevant:

* Support/system reports
* Messages requiring admin response
* Platform announcements
* Important system notifications
* Maintenance announcements
* Appropriate system activity

Do not overload dashboards with unnecessary information.

Prioritize actionable items.

---

# 16. Notification Types

Use meaningful notification categories/types so the frontend can display the appropriate icon, text, and destination.

Examples:

```text id="6y1y2s"
COURSE_ENROLLMENT
COURSE_CONTENT_PUBLISHED
COURSE_ACTIVITY
LESSON_STARTED
LESSON_COMPLETED
QUIZ_STARTED
QUIZ_COMPLETED
QUIZ_PASSED
QUIZ_FAILED
ASSIGNMENT_SUBMITTED
ASSIGNMENT_GRADED
LESSON_COMPLETED
MODULE_COMPLETED
COURSE_COMPLETED
CERTIFICATE_ELIGIBLE
CERTIFICATE_AVAILABLE
RETAKE_REQUIRED
ANNOUNCEMENT
NEW_MESSAGE
SUPPORT_REPLY
SYSTEM_MAINTENANCE
SYSTEM_ANNOUNCEMENT
```

Only create types that actually fit the application's functionality.

---

# 17. Avoid Notification Spam

Automation must be intelligent.

Do not notify users repeatedly for the same state.

For example, if a student opens the same lesson five times, do not necessarily generate five identical instructor notifications.

Distinguish between:

* An activity event
* A meaningful state change
* A repeated action
* A new content publication
* An ordinary content edit

Only notify when the event is meaningful according to the LMS workflow.

For instructor content:

* New content published → notify enrolled students.
* Existing content edited → only notify if the change is meaningful and the LMS rules justify it.
* Content viewed repeatedly → do not repeatedly notify.
* Content unpublished → handle according to the appropriate course/content rules.

---

# 18. Reliability & Idempotency

The communication and automation system must remain reliable if:

* User double-clicks
* Browser refreshes
* Request is retried
* Network temporarily fails
* Multiple requests arrive at the same time
* A user opens multiple tabs
* An event is accidentally processed more than once

Prevent:

* Duplicate messages
* Duplicate notifications
* Duplicate grading actions
* Duplicate certificates
* Duplicate completion records
* Inconsistent progress states

Use transactions, unique constraints, idempotency keys, or other appropriate mechanisms based on the existing architecture.

---

# 19. Responsive & Accessible UI

All messaging, notifications, announcements, and automation-related UI must work across:

* Desktop
* Laptop
* Tablet
* Mobile
* Small mobile screens

Ensure:

* Proper alignment
* No horizontal overflow
* Readable text
* Usable buttons
* Accessible controls
* Keyboard accessibility where appropriate
* Clear loading states
* Clear empty states
* Clear error states

Follow the existing flat LMS design system.

Do not introduce an unrelated visual style.

---

# 20. Important Implementation Requirements

Before implementing, inspect the existing:

* User roles
* Enrollment system
* Course/module structure
* Lesson system
* Quiz system
* Assignment system
* Grading logic
* Progress tracking
* Certificate logic
* Dashboards
* Database schema
* Authentication/authorization
* Existing notification components
* Existing messaging components
* Existing reusable UI components

Reuse the existing architecture where possible.

Do not create duplicate systems for functionality that already exists.

Do not hardcode relationships that should come from the database.

Do not bypass existing business rules.

---

# 21. Testing

Test the complete flows for:

### Student

* Enrollment
* Course communication
* Lesson activity
* Quiz activity
* Assignment submission
* Passing/failing
* Retakes
* Completion
* Certificate eligibility
* Certificate availability
* New instructor content notifications
* Announcements
* Messages
* Notifications

### Instructor

* Receiving student activity notifications
* Grading assignments
* Publishing lessons/content
* Publishing quizzes/assignments
* Students receiving content notifications
* Course announcements
* Student communication
* Completion updates
* Messaging

### Admin

* Support/report conversations
* Replies
* Global announcements
* Maintenance announcements
* Appropriate notifications

Also test:

* Duplicate submissions
* Repeated clicks
* Concurrent requests
* Multiple tabs
* Refresh during actions
* Unauthorized access
* Invalid resource IDs
* Notification duplication
* Incorrect completion states
* Content published to the wrong audience
* Notifications sent to unenrolled students

---

# FINAL EXPECTATION

The finished system should behave like a **real LMS communication and automation layer**, not a collection of manually triggered notifications.

The principle should be:

> **Users perform learning activities → the LMS understands what happened → business rules determine what it means → the appropriate progress/grade/completion state is updated → the appropriate people are automatically notified.**

For instructor content:

> **Instructor publishes content → LMS identifies currently enrolled students → appropriate notification is automatically generated → student can open the new content directly.**

Instructors should focus on teaching and decisions that require human judgment.

Students should receive clear feedback about their learning progress, new course content, announcements, results, and required actions.

Administrators should be able to handle system-level communication and support.

Keep the implementation **clean, maintainable, responsive, secure, event-driven, and consistent with the existing LMS architecture**.

Do not unnecessarily introduce manual workflows where the system can safely automate them.
