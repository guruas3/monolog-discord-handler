<?php

namespace sachaw\MonologDiscord\Laravel;

use Monolog\Logger;
use sachaw\MonologDiscord\DiscordHandler;

class DiscordChannel
{
	public function __invoke(array $config)
	{
		$log = new Logger('discord');
		$log->pushHandler(new DiscordHandler($config['webhook'], $config['level'] ?? Logger::ERROR, $config['bubble'] ?? true));

		return $log;
	}
}
