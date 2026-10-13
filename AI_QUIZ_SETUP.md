# Gemini quiz drafts

Use **Quiz Builder → Questions → Generate with AI** to generate multiple-choice drafts from lesson text, PDF, or UTF-8 TXT files. Review the preview, click **Add questions to quiz**, edit questions, then preview and save normally. Generation never saves or distributes a quiz.

## Your setup steps

1. Open [Google AI Studio API keys](https://aistudio.google.com/api-keys), sign in, and create a key in a project on the **Free tier**. Check the project's billing/tier before use; use a project without paid billing for a no-charge setup. QuizWeb cannot override Google's billing settings.
2. Sign in as an **administrator**, open **Administration → AI settings**, paste the Gemini key, leave the default model, enable generation, and click **Save AI settings**. The key field stays blank afterward; leaving it blank on later saves preserves the saved key. Use **Test saved connection** to check API-key/model access without generating questions. This does not verify generation quality, remaining quota, or free-tier status.
3. Configuration takes effect on the next request, without an Apache restart. The primary model defaults to `gemini-3.8-flash`, with `gemini-3.5-flash-lite` as a backup for unavailability/timeouts. Both have a documented free tier; actual project access and quotas still apply. Leave the backup field blank to disable failover. Existing settings gain the default backup when this update is deployed. **Test quiz generation** generates and validates 3 sample questions with the saved settings; it uses Google's quota, including a backup call if needed, but does not save a quiz or consume a teacher allowance. For automatic deployments, retain your database and the server encryption secret described below.
4. Sign in as a teacher, open your classroom's Quiz Builder, select **Standard Quiz** or another multiple-choice mode, then open Questions. Generate 6 mixed questions and verify their answers/explanations against the lesson before saving.

Official references: [API key setup](https://ai.google.dev/gemini-api/docs/api-key), [pricing/free tier](https://ai.google.dev/gemini-api/docs/pricing), [document input](https://ai.google.dev/gemini-api/docs/document-processing), [structured output](https://ai.google.dev/gemini-api/docs/structured-output).

## Supported inputs and output

- 3–20 questions, English/Filipino, easy/medium/hard/mixed, optional topic focus.
- Mastery Ladder requires at least 4 mixed questions including Master. Standard mixed drafts include Easy, Medium and Hard, suitable for the teacher-enabled adaptive practice bank.
- Four distinct options, one correct answer, topic, explanation, and 10 editable points.
- Text/TXT: 60 KB UTF-8, at least 100 characters. PDF: 5 MB. Convert Word/PowerPoint to PDF first. XAMPP's `upload_max_filesize` and `post_max_size` must permit the upload (at least 5 MB and 8 MB respectively).
- Existing questions are preserved when importing drafts, except a completely empty initial placeholder. The quiz limit remains 40 questions. Teachers verify factual accuracy; schema validation does not guarantee it.

## Limits and data handling

The app allows 10 generation requests per teacher per Manila calendar day, at least 30 seconds apart. Database quotas apply across sessions. The `ai_quiz_usage` table is added through normal schema startup. Generation uses lower reasoning on supported models and an output limit scaled to the question count. With a backup enabled, the primary has up to 30 seconds; a busy HTTP 503 or timeout switches to the backup (up to 25 seconds per call). HTTP 503 can receive up to two retries with increasing delays/jitter, respecting Google's `Retry-After`. All attempts share a 65-second generation budget and one application reservation. A backup timeout is not repeated; key/configuration/quota/DNS/TLS errors do not trigger failover. With backup disabled, the primary retains its 60-second timeout and HTTP 503 retries.

The application reservation is restored after confirmed HTTP 503 rejections or a connection failure before any request was sent. The 30-second cooldown remains. If any attempt timed out after being sent, it may have generated at Google and cannot be refunded as a definite rejection, even if the backup subsequently rejects the request. Other rejections and blocked/incomplete responses still count. Invalid input, missing key, and local permission failures do not count. Google's project quotas also apply to every provider call and may be lower; restoring an application allowance cannot restore Google's quota. The backup uses the same Google key/project and lesson source. No billing tier change, external provider, or background generation is used.

Only selected lesson content and generation settings go to Google. Uploads are read from PHP temporary storage, without a permanent lesson upload. `store: false` disables provider interaction storage, but Google's free-tier data-use terms still apply. Avoid student personal information in the lesson source. Only quota counts go into the usage table; generated questions are saved only through the normal teacher save workflow.

The server checks teacher ownership and CSRF, source type/size, provider completion, question count, duplicate questions/options, answer indexes, difficulty coverage, and existing builder validation. API keys and provider error bodies are never sent to the browser. Generation does not change published grades or award engagement points.

## Administrator configuration and encryption

Admin-stored settings take priority over `GEMINI_API_KEY`, `QUIZWEB_GEMINI_MODEL`, `QUIZWEB_GEMINI_BACKUP_MODEL`, and the optional `includes/gemini.local.php` legacy defaults. The saved key is encrypted with AES-256-GCM inside the existing `academic_settings` table, under `ai_quiz_settings`. Admin-only actions verify active role and CSRF. Settings changes/removal and successful generation tests are audited with action names only, without keys. The teacher panel and administrator HTML never receive the saved key or ciphertext.

The API key field accepts standard and authorization keys without assuming a prefix or a legacy key length. Keys are treated as opaque printable ASCII tokens up to 4096 characters. Surrounding paste whitespace is trimmed; internal whitespace/control characters are rejected for HTTP-header safety. Use **Test saved connection** to ask Google to validate the key and model access.

For local XAMPP, the application creates `data/ai_master.local.php` on the first key save. It is a guarded PHP file that returns 404 when requested directly, ignored by Git and Docker. Back up this file securely alongside the database; losing it prevents reading the saved API key until it is replaced. Do not publish it or reuse it between unrelated installations.

For Render, a key derived from the existing server-side database password provides stable encryption across redeployments. Rotating that password requires replacing the saved Gemini key. You can use a dedicated stable secret instead: set `QUIZWEB_AI_ENCRYPTION_KEY` to a base64-encoded random 32-byte key in the service environment before saving/replacing the API key. Preserve that secret during redeploys and restores. Adding the dedicated secret does not migrate an existing key until you replace it in the admin form. Never store the encryption secret in the database or source control.

Legacy server defaults remain supported until settings are first saved in Administration. Production ignores local configuration files. The Docker image enables PHP cURL; `.env.example` documents variables, but this app does not automatically load `.env` files. Save and removal take effect for new requests; an already-running provider request may finish.

The Docker image explicitly installs and updates CA certificates. If local HTTPS calls fail, configure a valid CA bundle for PHP cURL via `curl.cainfo` in `php.ini`, then restart Apache. Keep TLS verification enabled. Connection code 28 means timeout, 6 means DNS resolution failure, 7 means connection failure, and 60/77 mean certificate verification/configuration failure. The teacher error contains only a safe explanation and numeric code, without raw cURL details.

A successful **Test saved connection** checks model metadata access, not a generation request. Generation errors display Google's HTTP status and a known status label without exposing the provider's raw message or private details. HTTP 400 indicates rejected request/settings; 404 indicates unavailable model/endpoint; 429 indicates Google quota; 5xx indicates a service failure. A `FAILED_PRECONDITION` label indicates a project/region/setup requirement. Do not assume every failure is fixed by reducing the question count or adding billing.

## Checks

```powershell
& C:/xampppp/php/php.exe tests/ai_quiz_test.php
& C:/xampppp/php/php.exe tests/ai_quiz_retry_test.php
& C:/xampppp/php/php.exe tests/ai_quiz_failover_test.php
& C:/xampppp/php/php.exe tests/admin_ai_configuration_test.php
& C:/xampppp/php/php.exe tests/ai_quiz_refund_test.php
& C:/xampppp/php/php.exe tests/ai_quiz_database_test.php
& C:/xampppp/php/php.exe tests/admin_ai_test.php
& C:/xampppp/php/php.exe tests/ai_key_storage_test.php
& C:/xampppp/php/php.exe tests/ai_key_validation_test.php
node tests/ai_quiz_ui_test.cjs
node tests/admin_ai_ui_test.cjs
```

Provider response tests use fixtures, without API calls. Live generation requires your key and consumes quota.
