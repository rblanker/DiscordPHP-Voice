# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `VoiceException` marker interface — all library exceptions now implement `Discord\Voice\Exceptions\VoiceException` for unified catch blocks
- `examples/play-file.php` and `examples/record.php` — working code examples
- `docs/AUDIO_PIPELINE.md` — Mermaid diagrams for outbound/inbound audio pipeline, playback state machine, and audio format chain
- `docs/TROUBLESHOOTING.md` — platform-specific fix guide for all runtime exceptions (libdave, ffmpeg, opus, libsodium, permission errors)
- PHPStan level 5 static analysis (`composer phpstan`)
- `composer lint` script via `overtrue/phplint ^9.7`; folded into `composer check` aggregate
- `composer check` aggregate script (cs:check + lint + unit)
- `composer cs:check` dry-run style check
- EditorConfig CI validation job
- Comprehensive test suite expansion (581 tests): new files cover voice exceptions, VoiceClient playback guards, VoiceClient receive flow, Manager join-channel permission checks, WS gateway handlers, WS heartbeat/resume seq_ack bookkeeping, UDP transport (IP discovery + sendBuffer), Packet encrypt/decrypt roundtrip, Ogg edge cases (BOS/EOS/continued/multi-segment), Dave Runtime callback overrides, small-parts (User / HeaderValuesEnum / Dave handles / UserConnected), and process wrappers (Ffmpeg / DCA / OpusFfi)
- `tests/Integration/VoiceConnectionTest.php` — live voice gateway connection tests (require `DISCORD_BOT_TOKEN` + `CHANNEL_ID` env vars)
- `Discord\Voice\Recording\RecordingFormat` — backed enum (`PCM`, `WAV`, `OGG`) with `extension()`, `label()`, and `requiresFfmpeg()` helpers
- `Discord\Voice\Recording\WavWriter` — pure-PHP per-user WAV file writer for Discord audio (48 kHz stereo s16le); used internally by `record()` when `RecordingFormat::WAV` is requested
- `VoiceClient::record()` now accepts an optional `?RecordingFormat $format` and `?callable $outputPath` — when both are provided, the client automatically creates per-user audio files (WAV via `WavWriter`; OGG via ffmpeg); bare `record()` call is 100% backward-compatible
- `tests/Unit/Voice/Recording/` — 19 new tests covering `RecordingFormat`, `WavWriter`, and `VoiceClient` format recording integration
- `CLAUDE.md` — Claude Code entry-point and architecture guide for AI-assisted development sessions
- Statement coverage raised to **83.26%** (with legacy BC files excluded; 78% raw). 667 unit tests + 1 live integration "full session" test that drives join → playFile → record → stopRecording → disconnect against a real voice channel
- New unit test files: `ByteBufferRoundtripTest`, `SocketFactoryTest`, `VoiceClientChannelOpsTest`, `VoiceClientPcmRecordingTest`, `VoiceClientPlayPathsTest`, `VoiceClientStateTest`, `VoiceClientReadOggOpusTest`, `WSGettersAndStubsTest` (Feature), `Dave/CoordinatorHandlersTest`, `Dave/RuntimeExtraCallbacksTest`
- Live integration: standalone integration tests gated behind `INTEGRATION_FULL=1` env var so the default integration run (with `DISCORD_BOT_TOKEN` + `CHANNEL_ID`) only executes the consolidated "full session" test, avoiding Discord's rapid-rejoin rate limits
- `phpunit.xml` — coverage source now excludes `src/Discord/Voice/Helpers/` (legacy duplicate) and `src/Discord/WebSockets/OpEnum.php` (legacy enum), mirroring the existing PHPStan exclusions

### Fixed

- `VoiceClient`: incorrect `DCA` class reference (was `Dca`)
- `VoiceClient`: float-to-int cast on timestamp calculation
- `RecieveStream`: `write()` and `pipe()` missing return values
- `VoiceClient::$ssrcToUserId` / `$speakingStatus` — `WS::handleSpeaking` now writes SSRC→user_id mappings via the new `VoiceClient::updateSpeakingStatus()` public method instead of direct property access (previously threw a fatal error whenever a SPEAKING payload included an `ssrc`)
- `Client\WS` — binary voice gateway frames now emit the `ws-binary-message` event so DAVE binary opcodes reach the application layer
- `Dave\State::$groupId` — fixed resolution via `isset()` on a magic `__get` property (always returned `false`); now reads directly from the backing store
- `Client\WS` — stale MLS proposals no longer cause an infinite `INVALID_COMMIT_WELCOME` loop; three consecutive proposal failures close the socket with a descriptive error instead
- `Client\WS` — first voice WebSocket connections send Identify while true reconnects send Resume with `seq_ack` when available
- `Client\WS` — outbound DAVE MLS packets are sent as binary WebSocket frames; Session Description now initializes libdave state and sends Opcode 26 key package
- `Client\WS` — DAVE transition ID `0` executes locally without waiting for Opcode 22; remote decryptors retain key ratchets and use passthrough grace during setup
- `Client\Packet` — inbound RTP extension payload is stripped after transport decryption and before DAVE frame decryption so libdave receives the expected media frame bytes
- `Processes\OpusFfi` — decoder handle is now created once per `channels:rate` combination and reused across all frames; previously creating and destroying the native Opus decoder on every 20 ms frame discarded SILK/CELT pitch predictors and produced audible buzzing/static on all recorded audio
- `Processes\OpusFfi` — PCM buffer size corrected from `$frameSize * $channels * 2` to `$frameSize * $channels`; removed spurious `* 2` that had confused "samples" with "bytes"
- `VoiceClient` — per-speaker Opus codec state is now isolated; each SSRC gets its own `OpusFfi` decoder instance (`$ffiDecoders[]`) so speakers no longer corrupt each other's codec state when multiple users are talking simultaneously
- `VoiceClient::$readOpusTimer` — property now initialized to `null`; previously accessing it in `reset()` before any playback had started threw "Typed property must not be accessed before initialization"
- `Dave\MediaCryptoService::decrypt` — DAVE auth-failure now drops the frame (returns `false`) instead of leaking the ciphertext bytes upstream as if they were plaintext Opus. The previous "passthrough on auth failure" behaviour produced audible garbage during MLS key rotation and weakened the E2EE guarantee
- `Helpers\Buffer::read` — reject pending reads immediately when the buffer is already closed, instead of parking a Deferred that never resolves. Fixes silent hangs in code paths that trigger an Ogg buffer close mid-read (e.g. ffmpeg subprocess exiting before producing a complete header)
- `VoiceClient::playOggStream` — attach a rejection handler to `OggStream::fromBuffer` so a buffer-close mid-handshake reaches the playback Deferred instead of leaking as an unhandled-promise-rejection notice
- `Processes\Ffmpeg::encode` — `file` retained in `-protocol_whitelist`. Removing it (an earlier security tightening) made local-file playback impossible because ffmpeg uses the `file:` protocol internally for absolute paths. SSRF defence-in-depth is enforced one layer up in `VoiceClient::playFile()` (URL scheme allowlist + private/reserved/loopback IP block)

### Changed

- `Discord\Voice\Client\UDP` — `final` modifier removed so the class can be subclass-mocked by PHPUnit for `readOggOpus` / `sendBuffer` unit tests. There is still only one concrete implementation; no public extension point is intended.
- **[BC break]** `VoiceClient::$speakingStatus` — narrowed from `public` to `protected`. Use `updateSpeakingStatus()` to update, or `\ReflectionProperty` to read from outside the class.
- **[BC break]** `VoiceClient::$ssrcToUserId` — narrowed from `public` to `protected`. Access via `\ReflectionProperty` if needed externally.
- **[BC break]** `VoiceClient::$voiceDecoders` — changed from untyped with `null` default to `public array $voiceDecoders = []`; code that tested `=== null` must be updated to `=== []` or `count() === 0`.
- CI now triggers on push and pull_request (previously workflow_dispatch only)
- PHP 8.0 removed from CI matrix (requires `^8.1.2`)
- `actions/checkout` bumped to v4
- `scripts/setup-libdave.sh` documented for Linux, macOS, Windows (x64 + ARM64)
- `CONTRIBUTING.md` expanded with full contributor guide

## [v8.0.5] - 2026-01-15

See [GitHub releases](https://github.com/discord-php/DiscordPHP-Voice/releases/tag/v8.0.5) for details.

## [v8.0.4] - 2026-01-15

See [GitHub releases](https://github.com/discord-php/DiscordPHP-Voice/releases/tag/v8.0.4) for details.

## [v8.0.3] - 2026-01-15

See [GitHub releases](https://github.com/discord-php/DiscordPHP-Voice/releases/tag/v8.0.3) for details.

## [v8.0.2] - 2026-01-14

See [GitHub releases](https://github.com/discord-php/DiscordPHP-Voice/releases/tag/v8.0.2) for details.

## [v8.0.1] - 2025-12-22

See [GitHub releases](https://github.com/discord-php/DiscordPHP-Voice/releases/tag/v8.0.1) for details.

## [v8.0.0] - 2025-12-08

See [GitHub releases](https://github.com/discord-php/DiscordPHP-Voice/releases/tag/v8.0.0) for details.

[Unreleased]: https://github.com/discord-php/DiscordPHP-Voice/compare/v8.0.5...HEAD
[v8.0.5]: https://github.com/discord-php/DiscordPHP-Voice/compare/v8.0.4...v8.0.5
[v8.0.4]: https://github.com/discord-php/DiscordPHP-Voice/compare/v8.0.3...v8.0.4
[v8.0.3]: https://github.com/discord-php/DiscordPHP-Voice/compare/v8.0.2...v8.0.3
[v8.0.2]: https://github.com/discord-php/DiscordPHP-Voice/compare/v8.0.1...v8.0.2
[v8.0.1]: https://github.com/discord-php/DiscordPHP-Voice/compare/v8.0.0...v8.0.1
[v8.0.0]: https://github.com/discord-php/DiscordPHP-Voice/releases/tag/v8.0.0
