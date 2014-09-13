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

	public function __construct($opt=[]) {
		$this->opt = array_merge([
			'curl_request' => '',
			'curl_parser'	=> function(){ return true; },
			'times_per_ip' => 1,
			'times'			=> 3,
			'sleep_per_ip'	=> 1,
			'sleep'			=> 10,
			'random_ua'		=> true
		], (array)$opt);
	}

	public function getRandomUserAgent() {
		return static::$user_agents[ rand(0, -1+count(static::$user_agents)) ];
	}

	protected function curlRequest($string) {
		if ($this->opt['random_ua']) {
			$string .= " -H 'User-agent: {$this->getRandomUserAgent()}' ";
		}

		exec("curl $string --silent --compressed --proxy socks5h://127.0.0.1:9050", $output);
		return implode("\n", $output);
	}

	protected function enableTor() {
		unlink('tor.log');
		$this->tor_pid = pcntl_fork();

		if (-1===$this->tor_pid) {
			throw new Exception('Unable to fork process for TOR.');
		}

		if (0===$this->tor_pid) {
			$this->startTorProcess();
		}

		$this->waitForTorNetwork();
	}

	protected function disableTor() {
		$this->killTorProcess();
	}

	private function waitForTorNetwork() {
		while (empty(
			exec('if [ -f tor.log ]; then cat tor.log | grep "Bootstrapped 100%"; fi'))
		) sleep(1);
	}

	private function startTorProcess() {
		$this->killTorProcess();
		exec('tor > tor.log');
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
			$wait = false;

			$this->enableTor();
			echo "Making requests this IP: " . $this->getIP() . "\n";

			for ($j=0; $j<$this->opt['times_per_ip']; $j++) {

				echo "Making CURL request...\n";
				$response = $this->curlRequest($this->opt['curl_request']);

				if (call_user_func($this->opt['curl_continue_per_ip'], $response)) {

					$wait = true;
					echo "Request has succeded!\n";
					sleep($this->opt['sleep_per_ip']);

				} else {

					echo "Request has failed caused by 'curl_continue_per_ip' callback.\n";
					break;

				}

			}

			$this->disableTor();
			if ($wait) {
				sleep($this->opt['sleep']);
			}
		}
	}

}
