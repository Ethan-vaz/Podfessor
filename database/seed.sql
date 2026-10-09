-- Sample data: semesters and the public sample courses.
-- Run after schema.sql: mysql -u <user> -p <database> < database/seed.sql

INSERT INTO semesters (id, name, start_date) VALUES
    (1, 'Fall 2025', '2025-09-01'),
    (2, 'Winter 2026', '2026-01-05'),
    (3, 'Summer 2026', '2026-05-04'),
    (4, 'Fall 2026', '2026-09-01');

INSERT INTO courses (id, user_id, semester_id, code, name, image_url, notes) VALUES
    (1, NULL, 1, 'BTI225', 'Web Programming Principles',
     'https://images.unsplash.com/photo-1523800503107-5bc3ba2a6f81?q=80&w=880&auto=format&fit=crop',
     'Web Programming Principles - Covers HTML, CSS, JavaScript, Forms, and DOM manipulation.'),
    (2, NULL, 1, 'BTI325', 'Web Programming Tools and Frameworks',
     'https://images.unsplash.com/photo-1493119508027-2b584f234d6c?q=80&w=880&auto=format&fit=crop',
     'Web Programming Tools and Frameworks - Focuses on Node.js, Express, and building REST APIs.'),
    (3, NULL, 1, 'MTH330', 'Calculus',
     'https://plus.unsplash.com/premium_photo-1668902223880-3c9a62ad2a29?q=80&w=880&auto=format&fit=crop',
     'Calculus - Discusses Limits, Derivatives, and Integrals.'),
    (4, NULL, 1, 'SYS310', 'System Design and Architecture',
     'https://images.unsplash.com/photo-1577375870519-0a9fdb747f51?q=80&w=880&auto=format&fit=crop',
     'System Design and Architecture - Explores System Components and Architecture Patterns.');

INSERT INTO course_topics (course_id, name, position) VALUES
    (1, 'HTML', 0), (1, 'CSS', 1), (1, 'JavaScript', 2), (1, 'Forms', 3), (1, 'DOM', 4),
    (2, 'Node.js', 0), (2, 'Express', 1), (2, 'REST APIs', 2),
    (3, 'Limits', 0), (3, 'Derivatives', 1), (3, 'Integrals', 2),
    (4, 'System Components', 0), (4, 'Architecture Patterns', 1);
