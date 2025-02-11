<?php
	$home = `printf ~`;
	putenv("HOME=$home");
	putenv('PATH=$PATH:/bin:/usr/bin:/usr/local/bin:$HOME/bin');
	
	define("JSON_PRETTIER", JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
	
	// Yaml Parser
	use Symfony\Component\Yaml\Yaml;
	use Symfony\Component\Yaml\Exception\ParseException;
	
	function settings($host=null) {
		$host = isset($host) ? $host : $_SERVER["HTTP_HOST"];
		$settings = (object) [
			'env' => "live",
			'twig' => []
		];
		
		switch ($host) {
			case contains(".test", $host):
				$settings->env = "local";
				$settings->twig = [
					'debug' => true
				];
			break;
			case contains("cremadesignstudio", $host):
				$settings->env = "proof";
			break;
		}
		
		return $settings;
	}
	
	
	// == STRINGS & ARRAYS =====================================================
	
	// https://stackoverflow.com/a/34499778
	function ordinal($number) {
		return date("S", mktime(0, 0, 0, 0, $number, 0));
	}
	
	// Returns a random array item
	function random($array) {
		return $array[rand(0, count($array) - 1)];
	}
	
	function slugify($string, $delimiter = '-') {
		return strtolower(trim(preg_replace('/[^\w]+/', $delimiter, $string), $delimiter));
	}
	
	
	// == SEARCH ===============================================================
	
	function contains($query, $string) {
		return strpos($string, $query) !== false;
	}
	
	// https://stackoverflow.com/a/55229702
	class Haystack extends \stdClass {
		public $value;
		
		public function __construct($value) {
			$this->value = $value;
		}
		
		public function contains($needle) {
			if (strpos($this->value, $needle) !== false) return $this;
		}
	}
	
	function findItem($obj, $query) {
		return $obj[array_search($query, array_column($obj, 'id'))];
	}
	
	
	// == FILE RELATED =========================================================
	
	function removeDir($target) {
		$directory = new RecursiveDirectoryIterator($target,  FilesystemIterator::SKIP_DOTS);
		$files = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($files as $file) {
			if (is_dir($file)) {
				rmdir($file);
			} else {
				unlink($file);
			}
		}
		rmdir($target);
	}
	
	function removeDirs($targets) {
		foreach ($targets as $dir) {
			if (!is_dir($dir)) {
				mkdir($dir, 0777, true);
			}
			
			removeDir($dir);
		}
	}
	
	
	// == LOAD DATA ============================================================
	
	// Load global and local variables into environment
	// Run print phpinfo(INFO_ENVIRONMENT); to see values of everything
	function loadEnv() {
		$homedir = `printf ~`;
		$basedir = dirname($_SERVER['DOCUMENT_ROOT']);
		
		$env_global = [];
		$env_local = [];
		
		if (file_exists("$homedir/.env")) {
			$env_global = parse_ini_file("$homedir/.env");
		}
		
		if (file_exists("$basedir/.env")) {
			$env_local = parse_ini_file("$basedir/.env");
		}
		
		$env = (object) array_merge($env_global, $env_local);
		
		foreach ($env as $key => $value) {
			putenv("$key=$value");
		}
	}
	
	// Return keys from 1Password CLI
	function loadkey($key) {
		if ($op = trim(shell_exec("which op"))) {
			$item = getenv($key . '_REF');
			return trim(shell_exec("op read $item 2>&1"));
		} else {
			$item = getenv($key . '_KEY');
			return $item;
		}
	}
	
	function loadData($filename, $basedir = "data/") {
		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		$contents = file_get_contents("$basedir$filename");
		
		if ($ext == 'yaml') {
			$contents = str_replace("\t", '    ', $contents);
			return Yaml::parse($contents);
		} else {
			return json_decode($contents, true);
		}
	}
	
	class FrontMatter extends \stdClass {
		public $data;
		public $content;
		
		// Methods
		function init($content) {
			// Get position of second instance of "---"
			$pos = strpos($content, '---', strpos($content, '---') + 1);
			$this->yaml = Yaml::parse(substr($content, 0, $pos));
			$this->content = substr($content, $pos+3);
		}
	}
	
	function jsonToCSV($data, $cfilename) {
		$fp = fopen($cfilename, 'w');
		$header = false;
		
		foreach ($data as $row) {
			if (empty($header)) {
				$header = array_keys($row);
				fputcsv($fp, $header);
				$header = array_flip($header);
			}
			
			fputcsv($fp, array_merge($header, $row));
		}
		
		fclose($fp);
		
		return;
	}
	
	function XMLtoJSON($url) {
		$xml_string = file_get_contents($url);
		$xml = simplexml_load_string($xml_string, 'SimpleXMLElement', LIBXML_NOCDATA);
		return json_decode(json_encode($xml), true);
	}
	
	
	// == DEBUGGING & LOGGING ==================================================
	
	/*/
		tail -f ~/desktop/logs.log & tail -f ~/desktop/logs.json | pygmentize -s -l json -O style=one-dark
		--OR--
		yarn logger (might be more reliable)
	/*/
	
	function logger($message) {
		if (contains('.test', $_SERVER["HTTP_HOST"])) {
			$project_root = dirname(dirname($_SERVER["SCRIPT_FILENAME"]));
			$logfile = "logs.log";
			
			if (is_array($message)) {
				$message = json_encode($message, JSON_PRETTIER);
				$logfile = "logs.json";
			}
			
			file_put_contents("$project_root/$logfile", $message . "\n");
		}
	}
	
	/*/
		$console = new HtmlConsole();
		$console->addMessage("Stephen");
		$console->addMessage((array) $options);
		$data['consoleString'] = $console->messages(true) ?? false;
	/*/
	
	class HtmlConsole extends \stdClass {
		public function __construct() {
			$this->messages = [];
		}
		
		public function addMessage($value) {
			$this->messages[] = $value;
		}
		
		public function size() {
			return count($this->messages);
		}
		
		public function messages($stringify = null) {
			if ($this->size() == 0) {
				return false;
			}
			
			if (isset($stringify)) {
				$messageString = "";
				
				foreach ($this->messages as $message) {
					$msg = json_encode($message, JSON_PRETTIER);
					$messageString .=  "console.log($msg);\n";
				}
				
				return "<script>$messageString</script>";
			} else {
				return $this->messages;
			}
		}
	}
	
	function console($message) {
		echo "<script>console.log(" . json_encode($message) . ")</script>";
	}
	
	function printJSON($data) {
		header('Content-Type: application/json');
		echo json_encode($data, JSON_PRETTIER);
	}
	
	function printData($filename) {
		printJSON(json_decode(file_get_contents("$filename"), true));
	}
	
	if (!function_exists('dd')) {
		function dd($input) {
			$type = gettype($input);
			
			if ($type === "object" || $type === "array") {
				printJSON($input);
			} else {
				echo $input;
			}
			
			exit();
		}
	}

?>
