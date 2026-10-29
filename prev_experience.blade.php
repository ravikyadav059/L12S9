Created At: 2026-10-28T18:11:19+05:30
Completed At: 2026-10-28T18:11:19+05:30
File Path: `file:///c:/Users/hp/Desktop/L12S9/resources/views/components/profile/experience.blade.php`
Total Lines: 938
Total Bytes: 51995
Showing lines 1 to 60
The following code has been modified to include a line number before every line, in the format: <line_number>: <original_line>. Please note that any changes targeting the original code should remove the line number, colon, and leading space.
1: @props([
2:     'user' => null,
3:     'isOwnProfile' => true,
4: ])
5: 
6: @php
7:     // Dummy initial experiences for UI preview step matching design reference
8:     $defaultExperiences = [
9:         [
10:             'id' => 'exp-admin-1',
11:             'designation' => 'Principal/Director of the Institute',
12:             'organization' => 'Tata Consultancy Services(TCS), Texas',
13:             'start_date' => 'December, 2025',
14:             'end_date' => 'December, 2025',
15:             'is_current' => false,
16:             'description' => 'dssfefssf',
17:             'logo_title' => 'TATA CONSULTANCY SERVICES',
18:             'logo_sub' => 'tcs',
19:         ],
20:         [
21:             'id' => 'exp-admin-2',
22:             'designation' => 'Freelance Content Writer',
23:             'organization' => 'Tata Institute of Fundamental Research, Mumbai (TIFR, Mumbai)',
24:             'start_date' => 'December, 2025',
25:             'end_date' => 'December, 2025',
26:             'is_current' => false,
27:             'description' => 'dfddf',
28:             'logo_title' => '',
29:             'logo_sub' => 'tifr',
30:         ],
31:         [
32:             'id' => 'exp-admin-3',
33:             'designation' => 'Associate Professor HR',
34:             'organization' => 'Tata Consultancy Services (TCS), Hyderabad',
35:             'start_date' => 'July, 2020',
36:             'end_date' => 'July, 2025',
37:             'is_current' => false,
38:             'description' => 'ddd',
39:             'logo_title' => 'TATA CONSULTANCY SERVICES',
40:             'logo_sub' => 'tcs',
41:         ],
42:     ];
43: @endphp
44: 
45: <div 
46:     x-data="{
47:         experiences: @js($defaultExperiences),
48:         isAdding: false,
49:         editingId: null,
50:         showUnsavedModal: false,
51:         originalSnapshot: '',
52: 
53:         // Form fields
54:         form: {
55:             id: null,
56:             designation: '',
57:             organization: '',
58:             start_date: '',
59:             end_date: '',
60:             is_current: false,
The above content does NOT show the entire file contents. If you need to view any lines of the file which were not shown to complete your task, call this tool again to view those lines.
