<?php

define("JSON_FORMAT", 'json');
define("XML_FORMAT", 'xml');
define("SID_LEN", 5);
define("DEV_LEN", 11);

class Controller
{
    private $data;
    private $http_accept;
    private $pdf_file_path;
    private $method;
    private $request;
    private $sid;

    private $developer;
     
    public function __construct()
    {
        $this->data              = array();
		$this->sid		 		 = false;
        $this->http_accept       = (strpos($_SERVER['HTTP_ACCEPT'], XML_FORMAT)) ?
																	XML_FORMAT : JSON_FORMAT;
        $this->method            = strtolower($_SERVER['REQUEST_METHOD']);

        $this->developer         = false;
        $this->http_content_disposition = false;
        $this->zip_filename      = "";

        // clean up and sanitize request
		//$this->request = expode('/', substr($_SERVER['REQUEST_URI']

		$uri = $_SERVER['REQUEST_URI'];

		global $Log;
		// Log the uri.
		$Log->writeURL($_SERVER['REQUEST_URI']);

		// get past "/api";
		$request = explode("/", substr($uri, 4));
		array_shift($request);

		// remove any specified system ID and set $sid
        // remove any specified developer mode and set $developer

		$i = 0;
		foreach ($request as $key => $value) {
			if ((strpos($value, '?sid=') !== false) || (strpos($value, '&sid=')) !== false) {
                $pos = strpos($value, '?sid=');
                if ($pos === false) {
                    $pos = strpos($value, '&sid=');
                }
                $len = strpos($value, '&', $pos + 1);
				if ($len === false) {
					$this->sid = substr($value, SID_LEN + $pos);
				} else {
					$this->sid = substr($value, SID_LEN + $pos, $len - (SID_LEN + $pos));
				}
                $this->sid = (int)$this->sid;
				unset($request[$i]);
			}
            if ((strpos($value, '?developer=') !== false) || (strpos($value, '&developer=')) !== false) {
                $pos = strpos($value, '?developer=');
                if ($pos === false) {
                    $pos = strpos($value, '&developer=');
                }
                $len = strpos($value, '&', $pos + 1);
                if ($len === false) {
                    $this->developer = substr($value, DEV_LEN + $pos);
                } else {
                    $this->developer = substr($value, DEV_LEN + $pos, 1);
                }
                $this->developer = (int)$this->developer;
                unset($request[$i]);
            }
			$i++;
		}
        // remove the GET options from the end of the request array (if haven't been removed yet)
        $requestLen = count($request);
        if (isset($request[$requestLen-1]) && (substr($request[$requestLen-1], 0, 1) === '?')) {
            unset($request[$requestLen-1]);
        }

		if ( isset( $request[1] ) && !strpos($request[1],'?') === false ) {
			$request[1] = substr($request[1], 0, strpos($request[1],'?'));
		} elseif ( isset( $request[0] ) && !strpos($request[0],'?') === false ) {
			$request[0] = substr($request[0], 0, strpos($request[0],'?'));
		}
		if (isset($request[0]) && !$request[0] == null) {
			// replace this
			$request[0] = $this->dbClean($request[0]);
		}
		if (isset($request[1]) && !$request[1] == null) {
			$request[1] = $this->dbClean($request[1]);
		}
		if (isset($request[2]) && !$request[2] == null) {
			$request[2] = $this->dbClean($request[2]);
		}
		$this->request = $request;

        switch ($this->method) {
            case 'get':
            	$this->data = $_GET;
            	break;
            case 'post':
            	$this->data = json_decode(file_get_contents( 'php://input'), true);
            	break;
            case 'put':
            	$this->data = json_decode(file_get_contents( 'php://input'), true);
            	break;
            case 'delete':
            	$this->data = json_decode(file_get_contents( 'php://input'), true);
            	break;
            default:
            	die(Controller::respond(400, '', 'application/json'));
            	break;
        }

        // Return object to the caller
        return $this;
    }
 

    public static function respond($status = 200, $body = '', $content_type = 'text/html',
                                   $setContentDisposition = true, $zipFile = '')
    {
        global $Log;
        // build the status header
        $httpCode = 'HTTP/1.1 ' . $status . ' ' . self::getHttpCode($status);
        // set the status header
        header($httpCode);
        // set the content type
        header('Content-type: ' . $content_type . '; charset=utf-8');
        // enable CORS (optional)
        header("Access-Control-Allow-Origin: *");
        if ($content_type == 'text/csv') {
            // Output CSV-specific headers
            header('Content-Disposition: attachment');
            header('Content-Length: ' . strlen($body));
        } else if ($content_type == 'application/pdf') {
            header('Content-Disposition: attachment; filename="report.pdf"');
            readfile('/var/www/html/api/includes/reports/tempReportPDF.pdf');
        } else if ($content_type == 'application/octet-stream') {
            if ($setContentDisposition) {
                $Log->writeVariableDBG('Setting Content to attachment');
                header('Content-Disposition: attachment');
                header('Content-Length: ' . strlen($body));
            }
        } else if ($content_type == 'application/zip') {
            if ($setContentDisposition) {
                if ($zipFile !== 'data' && $zipFile !== "") {
                    $Log->writeVariableDBG('zip attachment, name: ' . basename($zipFile) . ' and size ' . filesize($zipFile));
                } 
                else {
                    $Log->writeVariableDBG("zip attachment name not set");
                }
                header('Content-Transfer-Encoding: binary');
                header('Content-Disposition: attachment');
                if ($zipFile !== 'data' && $zipFile !== "") {
                    header('Content-Length: ' . filesize($zipFile));
                }
            }
        }

        // alternatively you could replace * with a allowed domain gained from your
        // authentication step and passed to this method as a forth property.
        //header("Access-Control-Allow-Origin: ".$allowed_domains);

        // pages with body are easy
        if ($body != '') {
            // clear output buffer for zip file data.
            if ($zipFile === 'data') {
                ob_clean();
                flush();
            }
            // send the body
            echo $body;
        }
        // If a zip file, read and then delete it.
        else if ($zipFile != '') {
            ob_clean();
            flush();
            readfile($zipFile);
            unlink($zipFile);
        }
        // we need to create the body if none is passed
        else
        {
            // servers don't always have a signature turned on
			//(this is an apache directive "ServerSignature On")
            $sign = ($_SERVER['SERVER_SIGNATURE'] == '') ?
					 $_SERVER['SERVER_SOFTWARE'] . ' Server at ' .
					 $_SERVER['SERVER_NAME'] . ' Port ' .
					 $_SERVER['SERVER_PORT'] : $_SERVER['SERVER_SIGNATURE'];
             
            // optional body messages
            $message = '';
            switch($status)
            {
                case 400:
                	$message = 'The API could not understand your request. ';
			        $message .= 'Please check the documentation to ensure your requests are well formed.';
                	break;
                case 401:
                	$message = 'You must be authorized to run this API.';
                	break;
                case 404:
                	$message = 'The requested URL is not a valid API.';
                	break;

                // add as many custom messaged as you have defined Http codes in getHttpCode()
            }
            // For unrecognized requests with no body, construct an error array, then convert to JSON.
            $body = array('status' => $status,
                            'status_code' => self::getHttpCode($status),
                            'message' => $message,
                            'server' => $sign);
            $body = json_encode($body);
            echo $body;
        }

	    exit;
    }

    public static function getHttpCode($status)
    {
        // these could be stored better, in an .ini file and loaded via parse_ini_file()
        $codes = Array(
        	100 => 'Continue',
        	101 => 'Switching Protocols',
        	200 => 'OK',
        	201 => 'Created',
        	202 => 'Accepted',
        	203 => 'Non-Authoritative Information',
        	204 => 'No Content',
        	205 => 'Reset Content',
        	206 => 'Partial Content',
        	300 => 'Multiple Choices',
        	301 => 'Moved Permanently',
        	302 => 'Found',
        	303 => 'See Other',
        	304 => 'Not Modified',
        	305 => 'Use Proxy',
        	306 => '(Unused)',
        	307 => 'Temporary Redirect',
        	400 => 'Bad Request',
        	401 => 'Unauthorized',
        	402 => 'Payment Required',
        	403 => 'Forbidden',
        	404 => 'Not Found',
        	405 => 'Method Not Allowed',
        	406 => 'Not Acceptable',
        	407 => 'Proxy Authentication Required',
        	408 => 'Request Timeout',
        	409 => 'Conflict',
        	410 => 'Gone',
        	411 => 'Length Required',
        	412 => 'Precondition Failed',
        	413 => 'Request Entity Too Large',
        	414 => 'Request-URI Too Long',
        	415 => 'Unsupported Media Type',
        	416 => 'Requested Range Not Satisfiable',
        	417 => 'Expectation Failed',
        	500 => 'Internal Server Error',
        	501 => 'Not Implemented',
        	502 => 'Bad Gateway',
        	503 => 'Service Unavailable',
        	504 => 'Gateway Timeout',
        	505 => 'HTTP Version Not Supported'
        );
        return (isset($codes[$status])) ? $codes[$status] : '';
    }
     
    public function getData()
    {
        return $this->data;
    }
     
    public function getRequest()
    {
        return $this->request;
    }
     
    public function getMethod()
    {
        return $this->method;
    }
     
    public function getHttpAccept()
    {
        return $this->http_accept;
    }

    public function setHttpCSV()
    {
        $this->http_accept = 'csv';
    }

    public function setHttpPDF( $pdf_full_path )
    {
        $this->http_accept = 'pdf';
        $this->pdf_file_path = $pdf_full_path;
    }

    public function setHttpRaw($passThrough)
    {
        global $Log;
        $this->http_accept = 'octet-stream';
        $this->http_content_disposition = !$passThrough;
        $Log->writeVariableDBG("set raw, passthrough: " . $passThrough);
    }

    public function setHttpZip($zipFile, $passThrough)
    {
        global $Log;
        $this->http_accept = 'zip';
        $this->http_content_disposition = !$passThrough;
        $this->zip_filename = $zipFile;
        $Log->writeVariableDBG("set raw, zipFile = " . $zipFile . " passthrough: " . $passThrough);
    }

    public function getContentDisposition()
    {
        return $this->http_content_disposition;
    }

    public function getZipFilename()
    {
        return $this->zip_filename;
    }

    private function dbClean($request)
    {
		return $request;
    }

    public function get_sid()
    {
        return $this->sid;
    }

    public function get_developer()
    {
        return $this->developer;
    }

    public static function get_error_codes($code)
    {
        $errorCodes = array(
            0 => 'Condition not met',
            1 => 'Invalid parameter',
            2 => 'Unsupported parameter',
            3 => 'Core returned a false',
            4 => 'Resource not found'
        );
        return (isset($errorCodes[$code])) ? $errorCodes[$code] : '';

    }
     

}

?>
