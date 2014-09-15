<?php

class Brutor {

	public static $user_agents = [
	"Mozilla/6.0 (Windows NT 6.2; WOW64; rv:16.0.1) Gecko/20121011 Firefox/16.0.1",
	"Mozilla/5.0 (Windows NT 6.2; WOW64; rv:16.0.1) Gecko/20121011 Firefox/16.0.1",
	"Mozilla/5.0 (Windows NT 6.2; Win64; x64; rv:16.0.1) Gecko/20121011 Firefox/16.0.1",
	"Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:14.0) Gecko/20120405 Firefox/14.0a1",
	"Mozilla/5.0 (Windows NT 6.1; rv:14.0) Gecko/20120405 Firefox/14.0a1",
	"Mozilla/5.0 (Windows NT 5.1; rv:14.0) Gecko/20120405 Firefox/14.0a1",
	"Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.13 (KHTML, like Gecko) Chrome/24.0.1290.1 Safari/537.13",
	"Mozilla/5.0 (Windows NT 6.2) AppleWebKit/537.13 (KHTML, like Gecko) Chrome/24.0.1290.1 Safari/537.13",
	"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_2) AppleWebKit/537.13 (KHTML, like Gecko) Chrome/24.0.1290.1 Safari/537.13",
	"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_4) AppleWebKit/537.13 (KHTML, like Gecko) Chrome/24.0.1290.1 Safari/537.13",
	"Mozilla/5.0 (Windows NT 6.2) AppleWebKit/536.3 (KHTML, like Gecko) Chrome/19.0.1061.1 Safari/536.3",
	"Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/536.3 (KHTML, like Gecko) Chrome/19.0.1061.1 Safari/536.3",
	"Mozilla/5.0 (Windows NT 6.1) AppleWebKit/536.3 (KHTML, like Gecko) Chrome/19.0.1061.1 Safari/536.3%",
	];

	public $opt = [];

	private static $tor_port = "9999";
	private static $tor_log = '/tmp/tor.log';
	private static $tor_max_attempt = 8;

	public function __construct($opt=[]) {
		$this->opt = array_merge([
			'curl_request' 			=> '',
			'curl_continue_per_ip'	=> function(){ return true; },
			'curl_continue'			=> function() { return true; },
			'times_per_ip' 			=> 1,
			'times'						=> 3,
			'sleep_per_ip'				=> 1,
			'sleep'						=> 10,
			'random_ua'					=> true
		], (array)$opt);

		if (empty($this->opt['curl_request'])) {
			throw new Exception("CURL request can't be empty!");
		}
	}

	public function getRandomUserAgent() {
		return static::$user_agents[ array_rand(static::$user_agents) ];
	}

	protected function curlRequest($string) {
		if ($this->opt['random_ua']) {
			$string .= " -H 'User-agent: " . $this->getRandomUserAgent() . "' ";
		}

		exec(sprintf("curl %s --silent --compressed --proxy socks5h://127.0.0.1:%s", $string, static::$tor_port), $output);
		return implode("\n", $output);
	}

	protected function enableTor() {
		@unlink(static::$tor_log);
		$this->tor_pid = pcntl_fork();

		if (-1===$this->tor_pid) throw new Exception('Tor failed to start: unable to fork process.');
		if (0===$this->tor_pid) $this->startTorProcess();

		$this->waitTorBootstrapped();
	}

	protected function disableTor() {
		$this->killTorProcess();
	}

	private function waitTorBootstrapped() {
		$attempt = 0;
		while (empty(exec('if [ -f ' . static::$tor_log . ' ]; then cat ' . static::$tor_log . ' | grep "Bootstrapped 100%"; fi'))) {
			if ($attempt>static::$tor_max_attempt) throw new Exception("Tor failed to start: activation timeout");

			sleep(1);
			$attempt++;
		}
	}

	private function startTorProcess() {
		$this->killTorProcess();
		exec('tor -f ' . escapeshellarg(__DIR__.'/torrc') . ' > ' . static::$tor_log);
		exit;
	}

	protected function killTorProcess() {
		system('killall tor 2&> /dev/null');
	}

	protected function getIP() {
		return $this->curlRequest('http://ipinfo.io/ip');
	}

	public function start() {
		for ($i=0; $this->opt['times']==='forever' ? true : $i<$this->opt['times']; $i++) {
			$wait_per_ip = false;

			try {
				$this->enableTor();

				echo "New IP is " . $this->getIP() . "\n";

				for ($j=0; $j<$this->opt['times_per_ip']; $j++) {
					echo "Making CURL request...\n";
					$response = $this->curlRequest($this->opt['curl_request']);

					echo "Request has succeded!\n";

					$continue = !!call_user_func($this->opt['curl_continue'], $response);
					$continue_per_ip = !!call_user_func($this->opt['curl_continue_per_ip'], $response);

					if ( ! $continue) {
						echo "Breaking (global) caused by 'curl_continue' callback.\n";
						break 2;
					}

					if ( ! $continue_per_ip) {
						echo "Breaking (per IP) caused by 'curl_continue_per_ip' callback.\n";
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
				echo $e->getMessage() . "\n";
			}
		}

		echo "Finished.\n";
	}

}
