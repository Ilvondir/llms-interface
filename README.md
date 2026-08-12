# LLMsInterface

LLMsInterface is a web application that provides a ChatGPT/Gemini-like chat UI over a locally hosted language model API. It is built with the Laravel framework. In addition to Laravel, Vue was also used. These frameworks were integrated using the Inertia framework that allows for building SPA applications despite following the MVC architecture. Authentication and account scaffolding come from Laravel Jetstream and Fortify. The application is designed to talk to an OpenAI-compatible chat endpoint (for example LM Studio exposed through a tunnel such as ngrok).

The application lets you set the model API base URL, tune generation parameters, edit the system prompt per conversation, and chat with streaming replies. Reasoning (thinking) is stored and shown separately from the answer and is never sent back into the model history. Response stats such as prompt/output tokens, tokens per second, and time-to-first-token are displayed under each assistant message.

Logged-in users persist conversations and prompts in the database (PostgreSQL in production / Sail; SQLite is fine for a quick local start). Guests can use the full chat experience with browser-local persistence only — their conversations are never written to server conversation tables.

The application can attach images when the upstream model supports vision. It also integrates HTTP MCP servers so the model can discover and call external tools (for example search or GitHub helpers) during a reply. Tool-call rounds stay visible in the transcript, while prior tool payloads are omitted from later prompt history to keep small local context windows usable.

Markdown replies are rendered on the frontend with Marked and sanitized with DOMPurify. Toast notifications use Vue Toastification.

A live instance is available at [https://chat.michal-komsa.xyz](https://chat.michal-komsa.xyz). You can try the chat there with your own OpenAI-compatible API base URL (for example a tunnel to a local LM Studio `/v1` endpoint).

## Used Tools

### Backend
- PHP 8.4
- Laravel 13.23.0
- Inertia.js (Laravel) 2.0.24
- Laravel Jetstream 5.5.3
- Laravel Fortify 1.37.3
- Laravel Sanctum 4.3.3
- Laravel MCP 0.9.1
- Ziggy 2.6.3
- Sail 1.64.0
- PHPUnit 12.5.33

### Frontend
- HTML 5
- CSS 3
- JavaScript (ES modules)
- Vue 3.5.40
- Inertia.js (Vue) 2.3.27
- Tailwind CSS 3.4.19
- Vite 8.2.0
- Marked 18.0.9
- isomorphic-dompurify 3.19.0
- Vue Toastification 2.0.0-rc.5

## Requirements

For running the application you need:

- [PHP](https://www.php.net) 8.3+ (8.4 recommended)
- [Composer](https://getcomposer.org)
- [Node.js](https://nodejs.org) and npm
- [PostgreSQL](https://www.postgresql.org) (or SQLite for the simplest local setup)

Or only:

- [Docker](https://www.docker.com) (Laravel Sail)

You also need a reachable OpenAI-compatible chat API (for example LM Studio on your machine, optionally behind a tunnel).

## How to run

1. Execute command `git clone https://github.com/Ilvondir/llms-interface`.
2. Copy `.env.example` to `.env` and set `APP_KEY` (or let setup generate it).
3. Install and prepare the app:

```bash
composer setup
```

   This installs PHP/JS dependencies, creates `.env` if missing, generates the key, runs migrations, and builds frontend assets.

4. Start the local stack:

```bash
composer run dev
```

   This runs the PHP server, queue worker, log viewer, and Vite together.

5. Open the app URL from `.env` (`APP_URL`, usually `http://localhost`).
6. Point the sidebar API URL at your model endpoint (for example an ngrok URL ending with `/v1`), pick a model, and start chatting. Register an account if you want server-side conversation history; otherwise use guest mode.

You can also run this app on Docker containers using Laravel Sail (PostgreSQL included):

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

Optional demo user from the default seeder (`php artisan db:seed`):

| Account   | Email            | Password |
|:---------:|:----------------:|:--------:|
| Test User | test@example.com | password |

## First Look

Add screenshots under `public/firstlook/` and link them here, for example:

```markdown
![firstlook1](public/firstlook/firstlook1.png?raw=true)
![firstlook2](public/firstlook/firstlook2.png?raw=true)
![firstlook3](public/firstlook/firstlook3.png?raw=true)
```
