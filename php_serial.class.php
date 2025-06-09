<?php
/**
 * PHP Serial
 * 
 * @author Rémy Sanchez <then@then.com>
 * @license MIT
 * @link https://github.com/Xowap/PHP-Serial
 */
class PhpSerial
{
    private $_device = null;
    private $_dHandle = null;
    private $_winDevice = null;

    public function deviceSet($device)
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->_winDevice = $device;
            $this->_device = "COM" . str_replace("COM", "", $device);
        } else {
            $this->_device = $device;
        }
    }

    public function deviceOpen($mode = "r+b")
    {
        if ($this->_device === null) {
            throw new Exception("Device not set");
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->_dHandle = fopen($this->_device, $mode);
        } else {
            $this->_dHandle = fopen($this->_device, $mode);
        }

        if ($this->_dHandle === false) {
            throw new Exception("Unable to open device");
        }

        stream_set_blocking($this->_dHandle, true);
    }

    public function deviceClose()
    {
        if ($this->_dHandle !== null) {
            fclose($this->_dHandle);
            $this->_dHandle = null;
        }
    }

    public function sendMessage($message, $waitForReply = 0)
    {
        if ($this->_dHandle === null) {
            throw new Exception("Device not opened");
        }

        fwrite($this->_dHandle, $message);

        if ($waitForReply > 0) {
            sleep($waitForReply);
            $reply = '';
            while (!feof($this->_dHandle)) {
                $reply .= fread($this->_dHandle, 8192);
            }
            return $reply;
        }
    }

    public function confBaudRate($rate)
    {
        if ($this->_device === null) {
            throw new Exception("Device not set");
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("mode {$this->_winDevice} BAUD={$rate}");
        } else {
            exec("stty -F {$this->_device} {$rate}");
        }
    }

    public function confParity($parity)
    {
        if ($this->_device === null) {
            throw new Exception("Device not set");
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $parity = strtoupper($parity) == "NONE" ? "N" : $parity;
            exec("mode {$this->_winDevice} PARITY={$parity}");
        } else {
            $parity = strtoupper($parity) == "NONE" ? "-parity" : $parity;
            exec("stty -F {$this->_device} {$parity}");
        }
    }

    public function confCharacterLength($len)
    {
        if ($this->_device === null) {
            throw new Exception("Device not set");
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("mode {$this->_winDevice} DATA={$len}");
        } else {
            exec("stty -F {$this->_device} cs{$len}");
        }
    }

    public function confStopBits($bits)
    {
        if ($this->_device === null) {
            throw new Exception("Device not set");
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $bits = $bits == 1 ? "1" : "2";
            exec("mode {$this->_winDevice} STOP={$bits}");
        } else {
            $bits = $bits == 1 ? "-cstopb" : "cstopb";
            exec("stty -F {$this->_device} {$bits}");
        }
    }
}
?>