<?php

include 'Console.php';

class Brutor {

	protected $user_agents = [];
	protected $tor_config = null;
	protected $opt = [];

	public function __construct($opt = []) {

		$this->opt = array_merge([
		'curl_request' 			=> '',
		'curl_continue_per_ip'	=> function(){ return true; },
		'curl_continue'			=> function(){ return true; },
		'times_per_ip' 			=> 1,
		'times'						=> 3,
		'sleep_per_ip'				=> 1,
		'sleep'						=> 10,
		'random_ua'					=> true
		], (array)$opt);

		if (empty($this->opt['curl_request'])) {
			throw new Exception("CURL request can't be empty!");
		}

		$this->loadUserAgents();
		$this->loadTorConfiguration();

		@mkdir(__DIR__ . '/log');


	}

	protected function loadTorConfiguration() {
		$this->tor_config = [];
		foreach (@file(__DIR__ . '/torrc') ?: [] as $tor_opt) {
			if (preg_match('/(\w+)\s+(.+)/', $tor_opt, $m)) {
				$this->tor_config[ $m[1] ] = $m[2];
			}
		}
	}

	protected function loadUserAgents() {
		$this->user_agents = @file(__DIR__ . '/ua.txt') ?: [];
	}

	protected function getRandomUserAgent() {
		return $this->user_agents[ array_rand($this->user_agents) ];
	}

	protected function curlRequest($string) {
		if ($this->opt['random_ua']) {
			$string .= " -H 'User-agent: " . $this->getRandomUserAgent() . "' ";
		}

		exec(sprintf(
			"curl %s --silent --compressed --proxy socks5h://127.0.0.1:%s",
			$string,
			$this->tor_config['SocksPort'] ?: 9000
		), $output);
		return implode("\n", $output);
	}


	protected function enableTor() {
		Console::writeLine('Enabling TOR...');

		@unlink(__DIR__ . '/log/tor.txt');

		$tor_pid = pcntl_fork();
		if ($tor_pid === -1) {
			throw new Exception('Tor failed to start: unable to fork process.');
		}

		if ($tor_pid === 0) {
			// Child
			exec('tor -f torrc > log/tor.txt');
			exit;
		}


		$this->tor_pid = $tor_pid;
		$this->waitUntilTorIsBootstrapped();
	}

	protected function disableTor() {
		Console::writeLine('Disabling TOR.');
		if ($this->tor_pid) posix_kill($this->tor_pid, 9);
		exec('killall tor');
	}

	protected function waitUntilTorIsBootstrapped() {
		$warns = [];
		while (true) {

			sleep(1);
			$log_content = file_get_contents(__DIR__.'/log/tor.txt');

			if (preg_match('/Bootstrapped 100%/', $log_content)) {
				return true;
			}

			if (preg_match('/\[warn\] (.+)/', $log_content, $m)) {
				if (!in_array($m[1], $warns)) {
					$warns[] = $m[1];
					Console::writeLine($m[1], ConsoleColor::cyan);
				}
			}

			if (preg_match('/\[err\] (.+)/', $log_content, $m)) {
				throw new Exception("Tor activation error: " . $m[1]);
			}
		}
	}

	protected function getIP() {
		return $this->curlRequest('http://ipinfo.io/ip');
	}

	public function start() {
		$this->requests_count = 0;

		Console::writeTitle('Welcome to Brutor!');

		for ($i=0; $this->opt['times'] === 'forever' ? true : $i < $this->opt['times']; $i++) {
			$wait_per_ip = false;

			Console::writeLine("\nProcessing new request (" . (++$this->requests_count) . ")", ConsoleColor::purple);

			try {
				$this->enableTor();

				Console::write("New IP is: ");
				$this->ip = $this->getIP();
				Console::writeLine($this->ip, ConsoleColor::brown);

				for ($j = 0; $j < $this->opt['times_per_ip']; $j++) {
					Console::write("Making CURL request... ");
					$response = $this->curlRequest($this->opt['curl_request']);

					Console::writeLine("OK!", ConsoleColor::green);

					$continue = @call_user_func($this->opt['curl_continue'], $response) ?: false;
					if ($continue == false) {
						Console::writeLine("Breaking (globally) caused by 'curl_continue' callback.", ConsoleColor::cyan);
						break 2;
					}

					$continue_per_ip = false;
					$continue_per_ip = @call_user_func($this->opt['curl_continue_per_ip'], $response) ?: false;
					if ($continue_per_ip == false) {
						Console::writeLine("Breaking (per IP) caused by 'curl_continue_per_ip' callback.", ConsoleColor::cyan);
						break 1;
					}

					$wait_per_ip = true;
					sleep($this->opt['sleep_per_ip']);
				}

				$this->disableTor();

				if ($wait_per_ip) {
					sleep($this->opt['sleep']);
				}

			} catch (Exception $e) {
				Console::writeLine("\n" . $e->getMessage(), ConsoleColor::red);
				$this->disableTor();
			}
		}
	}

}
