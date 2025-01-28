<?php

namespace blobhive {

enum TokenId :int {
  case ARYSTA = 0x2c;
  case OBJSTA = 0x2d;
  case BLKEND = 0x3c;
  case DOCSTA = 0xbc;
  case DOCEND = 0xbd;
  case U16 = 0x90;
  case STR4B = 0xa2;
}

class Document {
  private bool $finished = false;
  private array $buff = [];
  
  public function __construct() {
    $this->pushRawTokenId(TokenId::DOCSTA);
    $this->pushRawU32(0x56484c42); // 'BNst'
    $this->pushRawU8(0x01); // version
    $this->pushRawU8(0x00); // flags
    $this->pushRawU8(0);
    $this->pushRawU8(0);
  }
  
  private function pushRawTokenId(TokenId $tid) {
    $this->pushRawU8($tid->value);
  }
  
  private function pushRawU8(int $value) {
    $value = floor($value);
    array_push($this->buff, $value & 0xff); $value >>= 8;
  }
  
  private function pushRawU16(int $value) {
    $value = floor($value);
    array_push($this->buff, $value & 0xff); $value >>= 8;
    array_push($this->buff, $value & 0xff); $value >>= 8;
  }
  
  private function pushRawU32(int $value) {
    $value = floor($value);
    array_push($this->buff, $value & 0xff); $value >>= 8;
    array_push($this->buff, $value & 0xff); $value >>= 8;
    array_push($this->buff, $value & 0xff); $value >>= 8;
    array_push($this->buff, $value & 0xff); $value >>= 8;
  }
  
  public function documentEnd() {
    if ($this->finished) return;
    $this->pushRawTokenId(TokenId::DOCEND);
    $this->pushRawU32(0);
    $this->pushRawU32(0);
  }
  
  public function u16(int $value) {
    $this->pushRawTokenId(TokenId::U16);
    $this->pushRawU16(0);
  }
  
  public function str($str) {
    $n = strlen($str);
    $this->pushRawTokenId(TokenId::STR4B);
    for ($i=0; $i<4; $i++) {
      if ($i<$n) {
        $this->pushRawU8(ord(substr($str, $i)));
      }
      else {
        $this->pushRawU8(0);
      }
    }
  }
  
  public function objectStart() {
    $this->pushRawTokenId(TokenId::OBJSTA);
  }
  
  public function objectEnd() {
    $this->pushRawTokenId(TokenId::BLKEND);
  }
  
  public function arrayStart() {
    $this->pushRawTokenId(TokenId::ARYSTA);
  }
  
  public function arrayEnd() {
    $this->pushRawTokenId(TokenId::BLKEND);
  }
  
  public function getString() {
    $this->documentEnd();
    $str = '';
    foreach ($this->buff as $b) {
      $str .= pack('C', $b);
    }
    return $str;
  }
}

}

?>
