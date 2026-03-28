🧪 LMS Black Box Test Plan (40 Tests)
📌 Scope

This test plan covers unit-level validation of core modules in a web-based Learning Management System (LMS):

Course Management
Student Interaction
Assessment Tools
Analytics Dashboard
🧩 1. Course Management (10 Tests)
ID	Test Name	Description	Expected Result
CM-01	Create Course	Create a new course with valid data	Course is created successfully
CM-02	Create Course Missing Fields	Create course with missing required fields	Validation error is shown
CM-03	Update Course	Update course details	Changes are saved correctly
CM-04	Delete Course	Delete an existing course	Course is removed from system
CM-05	Upload Material	Upload valid file (PDF, DOC)	File uploads successfully
CM-06	Upload Invalid File	Upload unsupported file format	Error message displayed
CM-07	Enroll Student	Add student to course	Student appears in enrollment list
CM-08	Duplicate Enrollment	Enroll same student twice	System prevents duplication
CM-09	Remove Student	Remove student from course	Student is removed successfully
CM-10	Course Listing	Retrieve list of courses	Correct course list is returned
💬 2. Student Interaction (10 Tests)
ID	Test Name	Description	Expected Result
SI-01	Create Forum Thread	Student creates discussion thread	Thread appears in forum
SI-02	Reply to Thread	User replies to thread	Reply is added correctly
SI-03	Empty Reply	Submit empty reply	Validation error shown
SI-04	Send Message	Send message to instructor	Message delivered successfully
SI-05	Receive Message	Retrieve messages	Messages displayed correctly
SI-06	Delete Message	Delete a message	Message removed successfully
SI-07	Unauthorized Access	Access another user’s messages	Access denied
SI-08	Notification Trigger	New message notification	Notification is generated
SI-09	Thread Pagination	Load multiple threads	Pagination works correctly
SI-10	Edit Post	Edit forum post	Updated content is saved
📝 3. Assessment Tools (10 Tests)
ID	Test Name	Description	Expected Result
AT-01	Create Quiz	Instructor creates quiz	Quiz saved successfully
AT-02	Add Questions	Add questions to quiz	Questions added correctly
AT-03	Submit Quiz	Student submits quiz	Submission recorded
AT-04	Auto Grading	System grades quiz	Score calculated correctly
AT-05	Manual Grading	Instructor grades assignment	Grade saved successfully
AT-06	Late Submission	Submit after deadline	System flags as late
AT-07	Invalid Submission	Submit empty assignment	Error displayed
AT-08	View Grades	Student views grades	Correct grades displayed
AT-09	Update Quiz	Modify quiz questions	Changes saved correctly
AT-10	Delete Quiz	Remove quiz	Quiz deleted successfully
📊 4. Analytics Dashboard (10 Tests)
ID	Test Name	Description	Expected Result
AD-01	Load Dashboard	Load analytics dashboard	Dashboard displays correctly
AD-02	Student Performance	View student performance metrics	Correct data shown
AD-03	Course Completion Rate	Calculate completion rate	Accurate percentage displayed
AD-04	Filter Data	Apply filters (date/course)	Filtered results shown
AD-05	Export Report	Export analytics report	File downloads successfully
AD-06	Real-time Update	Update metrics dynamically	Data refreshes correctly
AD-07	Empty Data	No data scenario	Proper empty state shown
AD-08	Role-based Access	Restrict dashboard access	Unauthorized users blocked
AD-09	Graph Rendering	Display charts/graphs	Charts render correctly
AD-10	Large Dataset	Handle large data volume	Performance remains stable
✅ Summary
Total Tests: 40
Coverage:
Course Management: 10
Student Interaction: 10
Assessment Tools: 10
Analytics Dashboard: 10