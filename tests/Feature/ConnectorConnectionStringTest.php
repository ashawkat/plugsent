<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * The plugin parses a single "connection string" (server::credential)
 * pasted from the dashboard. Both forms must resolve identically.
 */
class ConnectorConnectionStringTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (! defined('ABSPATH')) {
            define('ABSPATH', sys_get_temp_dir().'/');
        }

        require_once __DIR__.'/../../plugins/plugsent-connector/includes/class-plugsent-connector.php';
    }

    public function test_parses_server_and_credential(): void
    {
        $parsed = \Plugsent_Connector::parse_connection_string(
            'https://plugsent.betatech.co::plsk_abc123',
        );

        $this->assertSame(['https://plugsent.betatech.co', 'plsk_abc123'], $parsed);
    }

    public function test_parser_is_lenient_about_trailing_slashes_and_spaces(): void
    {
        $parsed = \Plugsent_Connector::parse_connection_string(
            '  https://plugsent.betatech.co/ :: PLSG-ABCDEF123456  ',
        );

        $this->assertSame(['https://plugsent.betatech.co', 'PLSG-ABCDEF123456'], $parsed);
    }

    public function test_rejects_strings_without_a_server_part(): void
    {
        $this->assertNull(\Plugsent_Connector::parse_connection_string('PLSG-ABCDEF123456'));
        $this->assertNull(\Plugsent_Connector::parse_connection_string(''));
        $this->assertNull(\Plugsent_Connector::parse_connection_string('https://only-a-server::'));
    }
}
