<?php

use Monolog\Logger;
use Monolog\Formatter\NormalizerFormatter;

class PDOLogHandler extends Monolog\Handler\AbstractProcessingHandler {
	/**
	 * @var ConnectionInterface
	 */
	private $db;

	/**
	 * @var string $table
	 */
	private $table;

	/**
	 * PDOLogHandler constructor.
	 * @param ConnectionInterface $db
	 */
	public function __construct(ConnectionInterface $db, $table, $level = Logger::DEBUG, $bubble = true) {
		$this->db = $db;
		$this->table = $table;
		parent::__construct($level, $bubble);
	}

	/**
	 * Writes the record down to the log of the implementing handler
	 *
	 * @param  array $record
	 * @return void
	 */
	protected function write(array $record) {
		$formatted = $record['formatted'];
		$this->db->insert("insert low_priority into $this->table (l_channel,l_level,l_message,l_date) values(:channel,:level,:message,:date)", [
			'channel' => $formatted['channel'],
			'level' => $formatted['level_name'],
			'message' => $formatted['message'],
			'date' => $formatted['datetime']
		]);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function getDefaultFormatter() {
		return new NormalizerFormatter();
	}

}