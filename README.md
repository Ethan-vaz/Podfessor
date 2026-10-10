# Podfessor

AI-powered course summaries you can listen to. Add your courses and notes, ask Podfessor about a topic, and it writes a short podcast-style lesson using your own notes as background, then reads it aloud with text-to-speech.

<!-- Add a screenshot or GIF of the app here, for example: ![Podfessor home page](docs/screenshot.png) -->

## Features

- **User accounts**: sign up, log in and log out, with hashed passwords and session cookies.
- **Your own courses**: add, edit and delete courses with topics and notes. Everyone also sees a set of sample courses.
- **Search and filter**: search by course name, code or topic, and filter by semester. Both run as SQL queries on the server.
- **Ask Podfessor**: pick one of your courses, ask a question, and get an AI-written podcast script that uses that course's topics and your notes.
- **Listen to lessons**: press Listen to turn a script into MP3 audio with OpenAI text-to-speech. Audio is generated once, saved and reused, so the same lesson is never paid for twice.
- **Episode library**: every script is saved as an episode you can replay, read or delete later.
- **Cost controls**: each account can generate a limited number of episodes per day.
- **Server-side API key**: the browser only talks to this app's PHP API, which calls OpenAI. The key never reaches the client.
- **Tested API**: 62 integration tests cover accounts, permissions, validation, search, the chat, audio generation and streaming, and the daily limit.

## Tech stack

| Layer | Technology |
|---|---|
| Front end | HTML, CSS, JavaScript (Fetch API) |
| Back end | PHP 8 REST API using PDO and cURL |
| Database | MySQL 8 / MariaDB |
| AI | OpenAI Responses API (scripts) and Audio Speech API (text-to-speech) |
| Testing | PHP integration tests against a real test database and a fake OpenAI server |

## How it works

```mermaid
flowchart LR
    B[Browser<br/>HTML + JavaScript] -- "JSON over fetch()<br/>session cookie" --> A[PHP REST API<br/>api/*.php]
    A -- "PDO prepared<br/>statements" --> D[(MySQL)]
    A -- "API key from<br/>environment" --> O[OpenAI<br/>Responses + Speech APIs]
    A -- "MP3 files" --> F[(Audio storage)]
```

### Listening to a lesson

```mermaid
sequenceDiagram
    participant B as Browser
    participant A as PHP API
    participant O as OpenAI
    participant D as MySQL + audio files
    B->>A: POST chat.php { prompt, course_id }
    A->>D: Load course topics and notes
    A->>O: Write a podcast script
    A->>D: Save it as an episode
    A-->>B: Episode with script
    B->>A: POST speech.php { episode_id } (Listen)
    alt audio already saved
        A-->>B: Existing audio link
    else first listen
        A->>O: Text-to-speech, in parts of up to 4,000 characters
        A->>D: Save MP3, store file name on episode
        A-->>B: New audio link
    end
    B->>A: GET audio.php?episode_id= (with Range for seeking)
    A-->>B: MP3, only to the episode's owner
```

### Database

```mermaid
erDiagram
    users ||--o{ courses : owns
    semesters ||--o{ courses : contains
    courses ||--o{ course_topics : has
    users ||--o{ episodes : creates
    courses |o--o{ episodes : "about"

    users {
        int id PK
        varchar name
        varchar email UK
        varchar password_hash
    }
    semesters {
        int id PK
        varchar name UK
        date start_date
    }
    courses {
        int id PK
        int user_id FK "NULL for sample courses"
        int semester_id FK
        varchar code
        varchar name
        varchar image_url
        text notes
    }
    course_topics {
        int id PK
        int course_id FK
        varchar name
        smallint position
    }
    episodes {
        int id PK
        int user_id FK
        int course_id FK "NULL if no course or course deleted"
        varchar prompt
        text script
        varchar audio_file "NULL until first listen"
        timestamp deleted_at "hidden but still counted for the limit"
    }
```

Courses with no `user_id` are sample courses every visitor can see. A user's own courses are visible only to them. Deleting a user removes their courses and episodes. Deleting a course removes its topics but keeps its episodes.

### API

All endpoints take and return JSON. Write requests must be sent as `application/json`.

| Method | Endpoint | Description | Login |
|---|---|---|---|
| `POST` | `/api/register.php` | Create an account and log in | |
| `POST` | `/api/login.php` | Log in | |
| `POST` | `/api/logout.php` | Log out | |
| `GET` | `/api/me.php` | The logged-in user, or `null` | |
| `GET` | `/api/semesters.php` | All semesters | |
| `GET` | `/api/courses.php?semester_id=&q=` | Courses you can see, with optional semester filter and search | |
| `GET` | `/api/courses.php?id=1` | One course | |
| `POST` | `/api/courses.php` | Create a course | ✓ |
| `PUT` | `/api/courses.php?id=1` | Update your course | ✓ |
| `DELETE` | `/api/courses.php?id=1` | Delete your course | ✓ |
| `POST` | `/api/chat.php` | `{ prompt, course_id? }` → write a script and save it as an episode | ✓ |
| `GET` | `/api/episodes.php` | Your 20 most recent episodes | ✓ |
| `DELETE` | `/api/episodes.php?id=1` | Delete an episode and its audio | ✓ |
| `POST` | `/api/speech.php` | `{ episode_id }` → generate (or reuse) the episode's audio | ✓ |
| `GET` | `/api/audio.php?episode_id=1` | Stream the episode's MP3, with `Range` support | ✓ |

Errors return a matching status code (`400`, `401`, `403`, `404`, `409`, `422`, `429`) and `{ "error": "message" }`. Validation errors also include `fields`, with one message per field.

### Security

- Passwords are hashed with `password_hash()` (bcrypt) and checked with `password_verify()`.
- Every query uses PDO prepared statements, including the search, which also escapes `%` and `_`.
- Session cookies are `HttpOnly` and `SameSite=Lax`, and the session ID changes at login.
- Write endpoints only accept JSON bodies, which blocks cross-site form submissions.
- Users can only see, change or delete their own courses. Sample courses are read-only.
- The chat and audio require login, so the OpenAI key can't be used by anonymous visitors, and each account has a daily episode limit.
- Audio files have random names, live outside the public folders, and are only served to the episode's owner.
- All user content is rendered with `textContent`, never as HTML.
- `.env`, the SQL files, the tests and the audio folder are never served to the browser.

## Running it locally

Requirements: PHP 8.1+ with the `pdo_mysql` and `curl` extensions, MySQL 8 or MariaDB 10.6+, and an [OpenAI API key](https://platform.openai.com/api-keys).

1. Clone the repo:

   ```bash
   git clone https://github.com/Ethan-vaz/Podfessor.git
   cd Podfessor
   ```

2. Create a database and user, then load the tables and sample data:

   ```bash
   mysql -u root -p -e "CREATE DATABASE podfessor; CREATE USER 'podfessor'@'localhost' IDENTIFIED BY 'change-me'; GRANT ALL ON podfessor.* TO 'podfessor'@'localhost';"
   mysql -u podfessor -p podfessor < database/schema.sql
   mysql -u podfessor -p podfessor < database/seed.sql
   ```

3. Copy `.env.example` to `.env` and fill in your database password and OpenAI key. The other settings are optional:

   | Setting | Default | What it does |
   |---|---|---|
   | `OPENAI_MODEL` | `gpt-5` | Model that writes the scripts |
   | `OPENAI_TTS_MODEL` | `gpt-4o-mini-tts` | Text-to-speech model |
   | `OPENAI_TTS_VOICE` | `coral` | Narrator voice |
   | `DAILY_EPISODE_LIMIT` | `30` | Episodes each account can create per day |
   | `AUDIO_DIR` | `storage/audio` | Where MP3 files are saved |

   If you set up the database before episodes were added, run `database/migrations/001_add_episodes.sql` instead of reloading the schema.

4. Start the server and open http://localhost:8000:

   ```bash
   php -S localhost:8000 router.php
   ```

   `router.php` stops the built-in server from serving private files such as `.env`. On Apache, `.htaccess` does the same job.

## Running the tests

The tests reset a separate `podfessor_test` database, start the app with a fake OpenAI server, and check every endpoint.

```bash
mysql -u root -p -e "CREATE DATABASE podfessor_test; GRANT ALL ON podfessor_test.* TO 'podfessor'@'localhost';"
TEST_DB_USER=podfessor TEST_DB_PASS=change-me tests/run.sh
```

## Project structure

```
├── index.html             Home page: course dashboard and chat
├── newCourse.html         Add or edit a course
├── login.html, signup.html
├── about.html
├── router.php             Blocks private files when using php -S
├── .env.example           Settings template: copy to .env
├── api/
│   ├── _bootstrap.php     Shared config, database connection, sessions, JSON helpers
│   ├── courses.php        Course CRUD, search and filtering
│   ├── semesters.php
│   ├── _episodes.php      Shared episode lookups and audio folder
│   ├── register.php, login.php, logout.php, me.php
│   ├── chat.php           Writes a script with OpenAI and saves it as an episode
│   ├── speech.php         Turns a script into MP3 audio (once) with OpenAI text-to-speech
│   ├── audio.php          Streams an episode's MP3 to its owner
│   └── episodes.php       Lists and deletes episodes
├── database/
│   ├── schema.sql         Tables, keys and indexes
│   ├── seed.sql           Semesters and sample courses
│   └── migrations/        Upgrades for databases created by older versions
├── javascript/
│   ├── common.js          API helper and account menu, used on every page
│   ├── app.js             Course cards, semester filter, search
│   ├── chat.js            Ask Podfessor panel and episode library with audio player
│   ├── courseForm.js      Add/edit course form
│   └── auth.js            Log in and sign up forms
├── css/styles.css
└── tests/
    ├── run.sh             Sets up the test database and servers, runs the tests
    ├── api_test.php       API integration tests
    └── mock_openai.php    Fake OpenAI server for the tests (scripts and audio)
```

## Roadmap

- [x] Store courses, notes and users in a MySQL database behind a REST API
- [x] Add and edit courses from the app
- [x] User accounts with secure login
- [x] Text-to-speech audio for each generated lesson
- [ ] Deploy to the cloud with a live demo link
