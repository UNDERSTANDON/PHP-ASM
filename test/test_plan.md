# LMS System Test Plan

This document outlines a 60-point test plan for the PHP-ASM Learning Management System. The tests are structured at a high level without focusing heavily on explicit variable names or granular code implementations, making it easier to follow for overall functional and integration testing.

## Authentication & User Management (1-10)
1. **Successful Registration:** Verify a user can successfully register with valid details.
2. **Registration Validation:** Verify registration fails when required fields are missing.
3. **Duplicate Email:** Verify registration fails when attempting to use an already registered email.
4. **Successful Login:** Verify login succeeds with valid, correct credentials.
5. **Invalid Password:** Verify login fails and shows an error with an incorrect password.
6. **Unregistered User:** Verify login fails for an email that does not exist in the system.
7. **Logout Functionality:** Verify clicking logout destroys the session and redirects the user safely.
8. **Protected Routes:** Verify attempting to access `dashboard.php` without an active session redirects to `login.php`.
9. **Password Rules:** Verify strict password creation constraints are enforced during registration.
10. **Session Expiry:** Verify prolonged inactivity results in an automatic logout.

## Roles & Access Control (11-20)
11. **Student Dashboard Access:** Verify a Student role can access the student dashboard view.
12. **Instructor Dashboard Access:** Verify an Instructor role uniquely accesses the instructor dashboard and analytics.
13. **Unauthorized Access Denial:** Verify a Student gets an "Access Denied" error if trying to load `course_analytics.php`.
14. **Role Recognition:** Verify system correctly sets the Role state variable during the authentication phase.
15. **Data Isolation (Instructor):** Verify instructors cannot access analytics or course data belonging to courses they are not assigned to.
16. **Data Isolation (Message):** Verify a user cannot access another user's private message context.
17. **Instructor View Students:** Verify an instructor can successfully view a roster of students enrolled in their courses.
18. **Student Restrictions:** Verify students are strictly prevented from viewing the global student roster.
19. **Course Settings Editing:** Verify an instructor has authorization to edit their course settings.
20. **Course Settings Protection:** Verify a student cannot edit course settings.

## Dashboard Functionality (21-30)
21. **Dashboard Loading:** Verify `dashboard.php` loads correctly without missing resources upon login.
22. **Welcome Message:** Verify the dashboard displays the correct user's name in the greeting.
23. **Navigation Links:** Verify all sidebar/header links route to the correct respective pages.
24. **Student Course List:** Verify a student's dashboard dynamically lists only the courses they are actively enrolled in.
25. **Instructor Course List:** Verify an instructor's dashboard lists only the courses they instruct.
26. **Quick Stats Accuracy:** Verify quantitative dashboard statistics (e.g., active courses, unread messages) are accurate.
27. **Activity Feed:** Verify recent activity feed loads appropriately without database errors.
28. **Empty States:** Verify empty data states (e.g., "No active courses") are handled gracefully without breaking the layout.
29. **Course Redirection:** Verify clicking a specific course tile navigates to the detailed view of that course.
30. **Action Buttons:** Verify interactive buttons on the dashboard trigger the intended functional workflow.

## Course Analytics (31-40)
31. **Analytics Loading:** Verify `course_analytics.php` properly loads and initializes for authorized users.
32. **Completion Aggregation:** Verify analytics correctly aggregate overall student completion percentages.
33. **Average Score Accuracy:** Verify the UI displays accurately computed average assessment scores for the class.
34. **Zero-Activity Exclusion:** Verify students with absolutely zero activity are omitted from the class average penalty calculation.
35. **Chart Rendering:** Verify graphic visualization (progress bars/charts) renders successfully based on valid data variables.
36. **Analytics Empty State:** Verify if a course has no data, a proper "No Data Available" message is displayed.
37. **Analytics Filtering:** Verify the ability to filter analytics data individually by course works as intended.
38. **Response Performance:** Verify loading large aggregated datasets via analytics does not drastically stall the page.
39. **Pagination:** Verify data grids/tables in analytics appropriately paginate if the volume of students is exceptionally high.
40. **Data Export:** Verify any export features (CSV/PDF) correctly download matching table datasets.

## Messaging System (41-50)
41. **Messaging UI Load:** Verify `messages.php` layout loads correctly.
42. **Send New Message:** Verify a user can successfully draft and send a message to another valid user.
43. **Receive Message:** Verify incoming messages properly populate the recipient's inbox queue.
44. **Unread Counter:** Verify the unread message indicator increments upon receiving a fresh message.
45. **Mark as Read:** Verify clicking to view a message triggers a state update marking it as "Read".
46. **Message Replies:** Verify users can seamlessly reply directly within a message thread UI.
47. **Empty Message Validation:** Verify attempting to fire an empty message payload results in a targeted validation warning.
48. **Self-Messaging Block:** Verify users are restricted from sending messages to themselves.
49. **Message Search:** Verify searching or basic inbox filtering operates correctly.
50. **Message Deletion:** Verify a user can delete a message to hide it from their view.

## Forums & Course Features (51-60)
51. **Forum Navigation:** Verify navigating from the course view into the forum functions properly.
52. **New Thread Creation:** Verify users are able to originate a new topic in a forum board.
53. **Thread Replies:** Verify users are able to reply to existing forum discussion threads.
54. **Instructor Moderation:** Verify instructors maintain deletion powers over inappropriate posts.
55. **Student Deletion Limits:** Verify students can only manage their own posts—not the posts of their peers.
56. **Course Payload Loading:** Verify syllabus and module structures correctly populate `course_data.php` inclusion mechanisms.
57. **Module Completion Tracking:** Verify students can mark standard modules as complete.
58. **Assessment Submissions:** Verify handing in graded materials safely communicates insertion to the database.
59. **Mobile Responsiveness:** Verify the CSS (`assets/app.css`) reflows major components properly on a simulated mobile viewpoint.
60. **Cross-Browser Verification:** Verify layout configurations and Javascript event listeners generally succeed across modern standard web browsers.
