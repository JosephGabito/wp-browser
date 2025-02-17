<?php

namespace cli;

use ClimoduleTester;

use tad\WPBrowser\Adapters\PHPUnit\Framework\Assert;
use function putenv;

class EnvironmentCest
{

    public function test_that_environment_is_inherited_from_putenv(ClimoduleTester $I)
    {
        if (PHP_VERSION_ID < 70000) {
            Assert::markTestSkipped('This test should only run on PHP >= 7.0');
            return;
        }

        putenv('BAZ=BIZ');

        try {
            $I->cli(['eval', '"var_dump(\$_SERVER[\'BAZ\'] ?? \'Not set\');"']);
            $I->seeInShellOutput('BIZ');
        } finally {
            putenv('BAZ');
        }
    }

    public function test_that_environment_is_inherited_from_ENV(ClimoduleTester $I)
    {
        if (PHP_VERSION_ID < 70100) {
            Assert::markTestSkipped('This test should only run on PHP >= 7.1');
        }

        $_ENV['FOO'] = 'BAR';

        try {
            $I->cli(['eval', '"var_dump(\$_SERVER[\'FOO\'] ?? \'Not set\');"']);
            $I->seeInShellOutput('BAR');
        } finally {
            unset($_ENV['FOO']);
        }
    }

    public function test_inheriting_env(ClimoduleTester $I)
    {
        if (PHP_VERSION_ID <= 70100) {
            Assert::markTestSkipped('This test should only run on PHP >= 7.1');
            return;
        }

        try {
            $_ENV['X_FOO'] = 'X_BAR';
            putenv('X_BAZ=X_BIZ');

            $I->cli(['eval', '"var_dump(\$_SERVER[\'X_FOO\'] ?? \'Not set\');"']);
            $I->seeInShellOutput('X_BAR');

            $I->cli(['eval', '"var_dump(\$_SERVER[\'X_BAZ\'] ?? \'Not set\');"']);
            $I->seeInShellOutput('X_BIZ');

            // putenv has a higher priority.
            putenv('X_FOO=X_BAR_putenv');

            $I->cli(['eval', '"var_dump(\$_SERVER[\'X_FOO\'] ?? \'Not set\');"']);
            $I->seeInShellOutput('X_BAR_putenv');
        } finally {
            unset($_ENV['X_FOO']);
            putenv('X_BAZ');
            putenv('X_FOO');
        }
    }

    public function test_that_wp_cli_config_variables_dont_prevent_inheriting_the_environment(ClimoduleTester $I)
    {
        if (PHP_VERSION_ID <= 70100) {
            Assert::markTestSkipped('This test should only run on PHP >= 7.1');
            return;
        }

        // This will set a custom env variable in WPCLI::buildProcessEnv()
        // which will be inherited by the child process.
        // This will cause proc_open to not receive to current env by default.
        $I->setCliEnv('disable-auto-check-update', '1');

        putenv('X_BOO=BAM');

        try {
            $I->cli(['eval', '"var_dump(\$_SERVER[\'X_BOO\'] ?? \'Not set\');"']);
            $I->seeInShellOutput('BAM');

            $I->cli(['eval', '"var_dump(\$_SERVER[\'WP_CLI_DISABLE_AUTO_CHECK_UPDATE\'] ?? \'Not set\');"']);
            $I->seeInShellOutput('1');
        } finally {
            putenv('X_BOO');
        }
    }

    public function test_that_per_process_environment_variables_can_be_set(ClimoduleTester $I)
    {
        if (PHP_VERSION_ID < 70000) {
            Assert::markTestSkipped('This test should only run on PHP >= 7.0');
            return;
        }

        $I->cli(['eval', '"var_dump(\$_SERVER[\'BAZ\'] ?? \'Not set\');"'], ['BAZ' => 'BIZ']);
        $I->seeInShellOutput('BIZ');

        putenv('BAZ=global_BIZ');

        try {
            // per process has priority.
            $I->cli(['eval', '"var_dump(\$_SERVER[\'BAZ\'] ?? \'Not set\');"'], ['BAZ' => 'BIZ']);
            $I->seeInShellOutput('BIZ');
        } finally {
            putenv('BAZ');
        }
    }

    public function test_that_global_env_variables_can_be_set_in_the_module(ClimoduleTester $I)
    {
        if (PHP_VERSION_ID < 70000) {
            Assert::markTestSkipped('This test should only run on PHP >= 7.0');
            return;
        }

        $I->haveInShellEnvironment(['FOO' => 'BAR']);

        $I->cli(['eval', '"var_dump(\$_SERVER[\'FOO\'] ?? \'Not set\');"']);
        $I->seeInShellOutput('BAR');

        $I->haveInShellEnvironment(['BAZ' => 'BIZ']);
        // Present for all commands
        $I->cli(['eval', '"var_dump(\$_SERVER[\'FOO\'] ?? \'Not set\');"']);
        $I->seeInShellOutput('BAR');
        // Merged between calls
        $I->cli(['eval', '"var_dump(\$_SERVER[\'BAZ\'] ?? \'Not set\');"']);
        $I->seeInShellOutput('BIZ');

        putenv('FOO=global_BAR');

        try {
            $I->cli(['eval', '"var_dump(\$_SERVER[\'FOO\'] ?? \'Not set\');"']);
            $I->seeInShellOutput('BAR');

            $I->cli(['eval', '"var_dump(\$_SERVER[\'FOO\'] ?? \'Not set\');"'], ['FOO' => 'BAR_PROCESS']);
            $I->seeInShellOutput('BAR_PROCESS');
        } finally {
            putenv('FOO');
        }
    }

    public function test_that_global_env_variables_can_be_passed_from_the_config_file(ClimoduleTester $I)
    {
        if (PHP_VERSION_ID < 70000) {
            Assert::markTestSkipped('This test should only run on PHP >= 7.0');
            return;
        }

        // This emulates having a FOO key under the env config.
        $I->setCliEnv('FOO', 'BAR');
        $I->setCliEnv('disable-auto-check-update', '1');

        $I->cli(['eval', '"var_dump(\$_SERVER[\'FOO\'] ?? \'Not set\');"']);
        $I->seeInShellOutput('BAR');

        $I->cli(['eval', '"var_dump(\$_SERVER[\'disable-auto-check-update\'] ?? \'Not set\');"']);
        $I->seeInShellOutput('Not set');

        $I->cli(['eval', '"var_dump(\$_SERVER[\'WP_CLI_DISABLE_AUTO_CHECK_UPDATE\'] ?? \'Not set\');"']);
        $I->seeInShellOutput('1');

        putenv('FOO=global_BAR');

        try {
            // per process putenv() DOES NOT HAVE priority. Use $I->haveInShellEnvironment() for that.
            $I->cli(['eval', '"var_dump(\$_SERVER[\'FOO\'] ?? \'Not set\');"']);
            $I->seeInShellOutput('BAR');

            putenv('FOO');

            $I->haveInShellEnvironment(['FOO' => 'BAR_SHELL_ENV']);

            // per process global env variables still have priority.
            $I->cli(['eval', '"var_dump(\$_SERVER[\'FOO\'] ?? \'Not set\');"']);
            $I->seeInShellOutput('BAR_SHELL_ENV');

            // per process env variables still have priority.
            $I->cli(['eval', '"var_dump(\$_SERVER[\'FOO\'] ?? \'Not set\');"'], ['FOO' => 'BAR_PROCESS']);
            $I->seeInShellOutput('BAR_PROCESS');
        } finally {
            putenv('FOO');
        }
    }

    public function test_that_inheriting_anything_from_the_global_environment_can_be_prevented(ClimoduleTester $I)
    {
        if (PHP_VERSION_ID < 70000) {
            Assert::markTestSkipped('This test should only run on PHP >= 7.0');
            return;
        }

        putenv('FOO=global_BAR');

        try {
            $I->cli(['eval', '"var_dump(\$_SERVER[\'FOO\'] ?? \'Not set\');"'], [], false);
            $I->seeInShellOutput('Not set');

            // This still works.
            $I->haveInShellEnvironment(['FOO' => 'BAR']);
            $I->cli(['eval', '"var_dump(\$_SERVER[\'FOO\'] ?? \'Not set\');"'], [], false);
            $I->seeInShellOutput('BAR');

            // WP_BROWSER_HOST request marker is still set.
            $I->cli(['eval', '"var_dump(\$_SERVER[\'WPBROWSER_HOST_REQUEST\'] ?? \'Not set\');"'], [], false);
            $I->seeInShellOutput('1');
        } finally {
            putenv('FOO');
        }
    }

    public function test_that_inheriting_some_global_environment_variables_can_be_prevented(ClimoduleTester $I)
    {
        if (PHP_VERSION_ID < 70100) {
            Assert::markTestSkipped('Blocking some global env vars is not possible on PHP < 7.1');
            return;
        }

        putenv('FOO=global_FOO');
        putenv('BAR=global_BAR');
        try {
            $I->dontInheritShellEnvironment(['FOO']);

            $I->cli(['eval', '"var_dump(\$_SERVER[\'FOO\'] ?? \'Not set\');"']);
            $I->seeInShellOutput('Not set');

            // Also blocked when passing explicit env
            $I->cli(['eval', '"var_dump(\$_SERVER[\'FOO\'] ?? \'Not set\');"'], ['SOMETHING' => 'WHATEVER']);
            $I->seeInShellOutput('Not set');

            $I->cli(['eval', '"var_dump(\$_SERVER[\'BAR\'] ?? \'Not set\');"']);
            $I->seeInShellOutput('global_BAR');
        } finally {
            putenv('FOO');
            putenv('BAR');
        }
    }

    public function test_that_inheriting_some_global_environment_variables_can_be_prevented_from_the_configuration(
        ClimoduleTester $I
    ) {
        if (PHP_VERSION_ID < 70100) {
            Assert::markTestSkipped('Blocking some global env vars is not possible on PHP < 7.1');
            return;
        }

        putenv('FOO_BLOCKED=global_FOO');
        try {
            $I->cli(['eval', '"var_dump(\$_SERVER[\'FOO_BLOCKED\'] ?? \'Not set\');"']);
            $I->seeInShellOutput('Not set');

            $I->cli(
                ['eval', '"var_dump(\$_SERVER[\'FOO_BLOCKED\'] ?? \'Not set\');"'],
                ['FOO_BLOCKED' => 'THIS_WORKS']
            );
            $I->seeInShellOutput('THIS_WORKS');

            $I->haveInShellEnvironment(['FOO_BLOCKED', 'THIS_WORKS_TOO']);

            $I->cli(
                ['eval', '"var_dump(\$_SERVER[\'FOO_BLOCKED\'] ?? \'Not set\');"'],
                ['FOO_BLOCKED' => 'THIS_WORKS_TOO']
            );
            $I->seeInShellOutput('THIS_WORKS_TOO');
        } finally {
            putenv('FOO_BLOCKED');
        }
    }
}
