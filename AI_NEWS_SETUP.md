# KAI News AI News Desk — Setup Guide

This guide covers the AI-assisted news monitoring feature in this project. It is designed for the local Windows/XAMPP setup at `C:\xampp\htdocs\kai_news`; adjust paths if your installation is elsewhere.

## What the feature does

1. Reads headlines and short excerpts from configured RSS feeds. It does **not** open or scrape full publisher articles.
2. Monitors the default Google News RSS searches for Rwanda, East Africa, and world news. You can add other trusted HTTPS RSS feeds in the AI News Desk.
3. Can ask the OpenAI API to prepare a short English and Kinyarwanda draft from the RSS information.
4. Keeps generated suggestions in the AI News Desk for a human editor to inspect.
5. Creates a `news` article with `status=draft` only when an editor chooses **Create unpublished draft for review**.

Nothing in this feature publishes an article automatically. Before publishing, an editor should verify the source, facts, wording, translation, category, and thumbnail. Published AI-assisted articles include a link to the original story.

## Requirements

- PHP 8 or later, MySQL/MariaDB, and a working KAI News installation.
- PHP extensions used by this feature: `curl`, `SimpleXML`, `openssl`, `mysqli`, and `mbstring`.
- Internet access from both Apache/PHP and the PHP CLI for RSS and OpenAI API requests.
- An OpenAI API key configured on the server to generate text. RSS scanning can be used without a key.
- A signed-in **superadmin** account to access **Admin → AI News Desk**.

## 1. Create an OpenAI API key

An API key is a private credential that lets this site call the OpenAI API on your behalf. You create it in your own OpenAI Platform account; do not send it to KAI News support, paste it into chat, or put it in the project.

1. Sign in to or create an account at [OpenAI Platform](https://platform.openai.com/).
2. In the Platform dashboard, create or select a project for KAI News.
3. Open the [API keys page](https://platform.openai.com/api-keys) and create a new secret key for that project. Use a clear name such as `kai-news-local`.
4. If the dashboard offers key permissions, use the narrowest permissions that still allow this app to make model responses. The app currently calls the Chat Completions API.
5. Copy the secret when it is shown and store it safely. Some dashboards show the full key only once. If you lose it, create a replacement key and revoke the lost one.

### Billing and costs

OpenAI API usage is billed separately from a ChatGPT subscription. A ChatGPT Plus/Pro plan by itself does not provide API credits. Follow the Platform's billing prompts and check current [API pricing](https://openai.com/api/pricing/) before enabling scheduled generation.

The app defaults to the `gpt-4o-mini` model. API cost depends on the model and the amount of text processed; review the OpenAI Platform's usage and billing controls. Start with manual runs and a conservative schedule. The scanner generates at most **3 suggestions per run**, but this is a per-run limit, not a daily spending cap. Configure any available budget alerts/limits in your OpenAI project and monitor actual usage.

## 2. Set the key in Windows for PHP

The application reads the key from the `OPENAI_API_KEY` environment variable. It is not saved in MySQL or in the project files.

### Recommended: Windows Environment Variables settings

1. On the Windows machine running XAMPP, open **Edit the system environment variables** from the Start menu.
2. In **System Properties → Advanced**, select **Environment Variables**.
3. Under **System variables**, choose **New** and set:
   - Variable name: `OPENAI_API_KEY`
   - Variable value: paste the secret API key from the OpenAI Platform.
4. Optionally add `OPENAI_MODEL` with a model name available to your OpenAI project. If you omit it, the application uses `gpt-4o-mini`.
5. Confirm the dialogs. Restart the XAMPP Control Panel and Apache so the web server inherits the updated environment.

If you run Apache as a Windows service under a different account, make sure the key is available to that service account (a system variable is usually simplest for a local machine). Protect access to Windows accounts that can read system environment settings.

Do **not**:

- Commit the key, put it in `includes/config.php`, or save it in a file under the web root.
- Put the key into a URL, browser page, RSS feed setting, screenshot, or support message.
- Share one key publicly or leave a leaked key active. Revoke it in the OpenAI Platform and replace it immediately if exposed.

To check that PHP has the required extensions, open PowerShell and run:

```powershell
& "C:\xampp\php\php.exe" -m | Select-String "curl|SimpleXML|openssl|mysqli|mbstring"
```

The PHP path above is the standard XAMPP path; use the path shown in your XAMPP installation if it differs.

## 3. Run a first manual test

1. Start Apache and MySQL in XAMPP.
2. Sign in with a superadmin account and open:
   `http://localhost/kai_news/admin/ai-news.php`
3. Confirm the page reports **API key detected**. If it does not, recheck the variable name, Windows account, and Apache restart.
4. Keep the preconfigured RSS feed lines initially. Choose **Save feeds and scan only**. The page reports the successful feed checks and any feed errors.
5. Choose **Generate up to 3 bilingual story drafts**. This sends up to three new RSS entries to the OpenAI API. Review generated text and source links in the queue.
6. For a story you want to work on, choose **Create unpublished draft for review**. Then review/edit the article, add a suitable thumbnail, and verify the source before publishing from the normal news editor.

Scanning and AI generation are separate buttons. A scan collects RSS entries; the generate action creates bilingual suggestions from unprocessed entries. The AI queue's **ready** status means a suggestion is ready for an editor—it is not yet an article on the public site. Only the editor action creates an actual unpublished article record.

If generation fails, read the message shown in the AI News Desk. Common causes include a missing key, API billing not enabled, an invalid/revoked key, an unavailable model, exhausted project limits, or a network/TLS issue.

## 4. Configure RSS sources

The default feeds are Google News RSS searches for Rwanda, East Africa, and the world. The feed box accepts up to 20 lines in this format:

```text
Label | https://public-host.example/rss.xml
```

Each feed must use HTTPS and a public hostname. Keep at least one valid feed configured. Choose credible publishers and check the links returned by a feed before using a story. Feed labels are also used to identify the story's location/scope in the queue.

## 5. Optional automatic feed scanning and generation

The CLI scanner in `cron\ai-news-scan.php` checks configured feeds and, when the key is available, prepares up to three new AI suggestions per run. It does **not** create or publish news articles; those remain editor-controlled actions in the AI News Desk.

First test the command manually in PowerShell:

```powershell
& "C:\xampp\php\php.exe" "C:\xampp\htdocs\kai_news\cron\ai-news-scan.php"
```

The process prints a JSON summary. A nonzero exit code indicates an error; check the output and the AI News Desk. Ensure the key is available to the Windows account running PHP CLI, not only the Apache process.

### Windows Task Scheduler

1. Open **Task Scheduler** and choose **Create Task**.
2. On **General**, name it `KAI News AI RSS Scan`. Set it to run under the Windows account that has access to the project and `OPENAI_API_KEY`.
3. On **Triggers**, create a repeating schedule. Start with an interval such as **60 minutes** while testing; only increase frequency after reviewing API usage and feed volume. The admin page mentions 15 minutes as an example, but more frequent runs may increase API usage.
4. On **Actions**, choose **Start a program**:
   - Program/script: `C:\xampp\php\php.exe`
   - Add arguments: `"C:\xampp\htdocs\kai_news\cron\ai-news-scan.php"`
   - Start in: `C:\xampp\htdocs\kai_news`
5. Save the task, run it once, and check **Last Run Result** and the task's history. For diagnosis, run the same command manually in PowerShell so its JSON/error output is visible.

The scheduled scanner is restricted to CLI and returns HTTP 404 if requested through a browser. Do not configure a public web URL to run the scan.

## Troubleshooting

| Symptom | What to check |
|---|---|
| AI News Desk says key not detected | Confirm the exact variable name `OPENAI_API_KEY`; restart Apache/XAMPP after setting it; make sure the key is available to the account running Apache. |
| Manual CLI works but the admin page does not | Apache may run as another Windows account or may not have been restarted after setting the environment variable. |
| Admin works but Task Scheduler fails | Check the task's run-as account, PHP executable path, project path, working directory, and whether that account can read the environment variable. |
| Feed scan reports errors | Verify the feed URL is valid HTTPS, the server has internet/DNS access, and the publisher still provides an RSS/Atom feed. |
| API returns an authentication or quota error | Check that the key is active, belongs to the intended project, API billing is configured, and project spending limits have not been reached. Never paste the key into an error report. |
| Story does not become an article | First generate a suggestion, then choose **Create unpublished draft for review**. A queue item marked **ready** is not yet in All News. |

## Official OpenAI resources

- [OpenAI Platform](https://platform.openai.com/)
- [API keys](https://platform.openai.com/api-keys)
- [API quickstart](https://developers.openai.com/api/docs/quickstart)
- [API pricing](https://openai.com/api/pricing/)

