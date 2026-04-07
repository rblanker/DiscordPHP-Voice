<?php

declare(strict_types=1);

/*
 * This file is a part of the DiscordPHP project.
 *
 * Copyright (c) 2015-2022 David Cole <david.cole1340@gmail.com>
 * Copyright (c) 2020-present Valithor Obsidion <valithor@discordphp.org>
 * Copyright (c) 2025-present Alexandre Candeias (Sky) <sky@discordphp.org>
 *
 * This file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

namespace Discord\Tests\Unit\Dave;

use Discord\Voice\Dave\Runtime;
use PHPUnit\Framework\TestCase;

final class RuntimeTest extends TestCase
{
    private ?string $originalDaveLibraryPath = null;

    protected function setUp(): void
    {
        $this->originalDaveLibraryPath = getenv('DISCORDPHP_DAVE_LIBRARY') ?: null;
        Runtime::reset();
    }

    protected function tearDown(): void
    {
        Runtime::reset();

        if ($this->originalDaveLibraryPath === null) {
            putenv('DISCORDPHP_DAVE_LIBRARY');

            return;
        }

        putenv('DISCORDPHP_DAVE_LIBRARY='.$this->originalDaveLibraryPath);
    }

    public function testEncryptDecryptAndCommitWelcomeCallbacksAreUsed(): void
    {
        Runtime::configureCallbacks(
            fn (string $frame, int $protocolVersion): ?string => "enc:{$protocolVersion}:{$frame}",
            fn (string $frame, int $protocolVersion): string => "dec:{$protocolVersion}:{$frame}",
            fn (string $payload, int $protocolVersion): ?string => "commit:{$protocolVersion}:{$payload}"
        );

        self::assertSame('enc:1:frame', Runtime::encryptMediaFrame('frame', 1));
        self::assertSame('dec:1:frame', Runtime::decryptMediaFrame('frame', 1));
        self::assertSame('commit:1:proposals', Runtime::buildMlsCommitWelcome('proposals', 1));
    }

    public function testProtocolZeroFallsBackToPassthroughOrNull(): void
    {
        self::assertSame('frame', Runtime::encryptMediaFrame('frame', 0));
        self::assertSame('frame', Runtime::decryptMediaFrame('frame', 0));
        self::assertNull(Runtime::buildMlsCommitWelcome('proposals', 0));
    }

    public function testNativeRuntimeLoadsAndReportsMaxProtocolVersion(): void
    {
        $this->requireNativeDaveRuntime();

        self::assertTrue(Runtime::isAvailable());
        self::assertSame(1, Runtime::maxProtocolVersion());
    }

    public function testNativeSessionLifecycleAndKeyPackageGeneration(): void
    {
        $this->requireNativeDaveRuntime();

        $session = Runtime::createSession();
        self::assertNotNull($session);

        try {
            self::assertTrue(Runtime::initializeSession($session, 1, 123, '111'));
            self::assertSame(1, Runtime::getSessionProtocolVersion($session));

            $keyPackage = Runtime::getMarshalledKeyPackage($session);
            self::assertIsString($keyPackage);
            self::assertNotSame('', $keyPackage);

            self::assertTrue(Runtime::resetSession($session));
        } finally {
            $session->destroy();
        }
    }

    public function testNativeEncryptorAndDecryptorPassthroughRoundTrip(): void
    {
        $this->requireNativeDaveRuntime();

        $encryptor = Runtime::createEncryptor();
        $decryptor = Runtime::createDecryptor();

        self::assertNotNull($encryptor);
        self::assertNotNull($decryptor);

        try {
            self::assertTrue(Runtime::configureEncryptorPassthrough($encryptor, true));
            self::assertTrue(Runtime::configureDecryptorPassthrough($decryptor, true));

            $frame = hex2bin('0dc5aedd5bdc3f20be5697e54dd1f437');
            self::assertIsString($frame);

            $encrypted = Runtime::encryptWithEncryptor($encryptor, $frame, 0);
            self::assertSame($frame, $encrypted);

            $decrypted = Runtime::decryptWithDecryptor($decryptor, $encrypted);
            self::assertSame($frame, $decrypted);
        } finally {
            $encryptor->destroy();
            $decryptor->destroy();
        }
    }

    private function requireNativeDaveRuntime(): void
    {
        $libraryPath = $this->originalDaveLibraryPath;
        if ($libraryPath === null || $libraryPath === '' || ! is_file($libraryPath)) {
            self::markTestSkipped('Native libdave runtime not configured for this test run.');
        }

        putenv('DISCORDPHP_DAVE_LIBRARY='.$libraryPath);
        Runtime::reset();
    }
}
