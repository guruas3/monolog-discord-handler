<?php

namespace sachaw\MonologDiscord;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class DiscordHandler extends AbstractProcessingHandler
{
	/**
	 * Discord webhook to post to.
	 * @var string
	 */
	private $webhook;

	/**
	 * Http client to connect to Discord.
	 * @var GuzzleHttp\Client
	 */
	private $client;

	/**
	 * Number of requests remaining within the rate limit.
	 * @var int
	 */
	private $rateLimitRemaining;

	/**
	 * Epoch time at which the rate limit resets.
	 * @var int
	 */
	private $rateLimitReset;

	protected $levelColors = [
			Logger::DEBUG => 13882323,
			Logger::INFO => 13882323,
			Logger::NOTICE => 13882323,
			Logger::WARNING => 13882323,
	    Logger::ERROR => 16007990,
	    Logger::CRITICAL => 2201331,
	    Logger::ALERT => 16771899,
	    Logger::EMERGENCY => 6765239,
	];

	public function __construct($webhook, $level = Logger::ERROR, bool $bubble = true)
	{
		$this->webhook = $webhook;
		$this->client = new Client();

		parent::__construct($level, $bubble);
	}

	protected function write(array $record)
	{
		if ($this->rateLimitRemaining == 0 && $this->rateLimitReset !== null) {
			$this->waitUntil($this->rateLimitReset);
		}

		try {
			$response = $this->send($record);
		} catch (ClientException $ex) {
			$response = $ex->getResponse();
			if ($response->getStatusCode() == 429) {
				$retryAfter = $response->getHeader('Retry-After')[0];
				$this->wait($retryAfter);

				$this->send($record);
			} else {
				throw $ex;
			}
		}

		$this->rateLimitRemaining = $response->getHeader('X-RateLimit-Remaining')[0];
		$this->rateLimitReset = $response->getHeader('X-RateLimit-Reset')[0];
	}

	private function send(array $record) {
		$formattedDate = $record['datetime']
			->setTimezone(new \DateTimeZone(date_default_timezone_get()))
			->format('Y-m-d h:i:s A');

		return $this->client->request('POST', $this->webhook, [
			'json' => $this->formatMessage($record),
		]);
	}

	private function formatMessage(array $record) {
		return [
			'embeds' => $this->formatEmbeds($record),
		];
	}

	private function formatEmbeds(array $record)
	{
		$fields = array();
		foreach ($record['context'] as $key => $value) {
			if (is_array($value)) {
				$value = json_encode($value);
			} else if (method_exists($value, '__toString')) {
				$value = (string)$value;
			}

			$fields[] = [
				'name' => $key,
				'value' => $value,
				'inline' => true,
			];
		}

		return [
			[
				'title' => $record['message'],
				'timestamp' => $record['datetime']->format(\DateTime::ATOM),
				'fields' => $fields,
				'footer' => [
					'text' => $record['level_name'],
				],
				"color" => $this->levelColors[$record['level']],
			]
		];
	}

	private function wait($microseconds)
	{
		usleep($microseconds);
	}

	private function waitUntil($timestamp)
	{
		time_sleep_until($timestamp);
	}
}
