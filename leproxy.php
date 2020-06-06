#!/usr/bin/env php
<?php
/**
 * LeProxy is the HTTP/SOCKS proxy server for everybody!
 *
 * LeProxy should be run from the command line. Assuming this file is
 * named `leproxy.php`, try running `$ php leproxy.php --help`.
 *
 * @link https://leproxy.org/ LeProxy project homepage
 * @license https://leproxy.org/#license MIT license
 * @copyright 2017 Christian Lück
 * @version 0.2.2
 */namespace React\Promise;
function resolve($promiseOrValue=null){
if($promiseOrValue instanceof ExtendedPromiseInterface){
return$promiseOrValue;
}
if(method_exists($promiseOrValue,'then')){
$canceller=null;
if(method_exists($promiseOrValue,'cancel')){
$canceller=[$promiseOrValue,'cancel'];
}
return new Promise(function($resolve,$reject,$notify)use($promiseOrValue){
$promiseOrValue->then($resolve,$reject,$notify);
},$canceller);
}
return new FulfilledPromise($promiseOrValue);
}
function reject($promiseOrValue=null){
if($promiseOrValue instanceof PromiseInterface){
return resolve($promiseOrValue)->then(function($value){
return new RejectedPromise($value);
});
}
return new RejectedPromise($promiseOrValue);
}
function all($promisesOrValues){
return map($promisesOrValues,function($val){
return$val;
});
}
function race($promisesOrValues){
$cancellationQueue=new CancellationQueue;
$cancellationQueue->enqueue($promisesOrValues);
return new Promise(function($resolve,$reject,$notify)use($promisesOrValues,$cancellationQueue){
resolve($promisesOrValues)->done(function($array)use($cancellationQueue,$resolve,$reject,$notify){
if(!is_array($array)||!$array){
$resolve();
return;
}
foreach($array as$promiseOrValue){
$cancellationQueue->enqueue($promiseOrValue);
resolve($promiseOrValue)->done($resolve,$reject,$notify);
}
},$reject,$notify);
},$cancellationQueue);
}
function any($promisesOrValues){
return some($promisesOrValues,1)->then(function($val){
return array_shift($val);
});
}
function some($promisesOrValues,$howMany){
$cancellationQueue=new CancellationQueue;
$cancellationQueue->enqueue($promisesOrValues);
return new Promise(function($resolve,$reject,$notify)use($promisesOrValues,$howMany,$cancellationQueue){
resolve($promisesOrValues)->done(function($array)use($howMany,$cancellationQueue,$resolve,$reject,$notify){
if(!is_array($array)||$howMany<1){
$resolve([]);
return;
}
$len=count($array);
if($len<$howMany){
throw new Exception\LengthException(sprintf('Input array must contain at least %d item%s but contains only %s item%s.',$howMany,1===$howMany?'':'s',$len,1===$len?'':'s'));
}
$toResolve=$howMany;
$toReject=($len-$toResolve)+1;
$values=[];
$reasons=[];
foreach($array as$i=>$promiseOrValue){
$fulfiller=function($val)use($i,&$values,&$toResolve,$toReject,$resolve){
if($toResolve<1||$toReject<1){
return;
}
$values[$i]=$val;
if(0===--$toResolve){
$resolve($values);
}
};
$rejecter=function($reason)use($i,&$reasons,&$toReject,$toResolve,$reject){
if($toResolve<1||$toReject<1){
return;
}
$reasons[$i]=$reason;
if(0===--$toReject){
$reject($reasons);
}
};
$cancellationQueue->enqueue($promiseOrValue);
resolve($promiseOrValue)->done($fulfiller,$rejecter,$notify);
}
},$reject,$notify);
},$cancellationQueue);
}
function map($promisesOrValues,callable$mapFunc){
$cancellationQueue=new CancellationQueue;
$cancellationQueue->enqueue($promisesOrValues);
return new Promise(function($resolve,$reject,$notify)use($promisesOrValues,$mapFunc,$cancellationQueue){
resolve($promisesOrValues)->done(function($array)use($mapFunc,$cancellationQueue,$resolve,$reject,$notify){
if(!is_array($array)||!$array){
$resolve([]);
return;
}
$toResolve=count($array);
$values=[];
foreach($array as$i=>$promiseOrValue){
$cancellationQueue->enqueue($promiseOrValue);
$values[$i]=null;
resolve($promiseOrValue)->then($mapFunc)->done(function($mapped)use($i,&$values,&$toResolve,$resolve){
$values[$i]=$mapped;
if(0===--$toResolve){
$resolve($values);
}
},$reject,$notify
);
}
},$reject,$notify);
},$cancellationQueue);
}
function reduce($promisesOrValues,callable$reduceFunc,$initialValue=null){
$cancellationQueue=new CancellationQueue;
$cancellationQueue->enqueue($promisesOrValues);
return new Promise(function($resolve,$reject,$notify)use($promisesOrValues,$reduceFunc,$initialValue,$cancellationQueue){
resolve($promisesOrValues)->done(function($array)use($reduceFunc,$initialValue,$cancellationQueue,$resolve,$reject,$notify){
if(!is_array($array)){
$array=[];
}
$total=count($array);
$i=0;
$wrappedReduceFunc=function($current,$val)use($reduceFunc,$cancellationQueue,$total,&$i){
$cancellationQueue->enqueue($val);
return$current
->then(function($c)use($reduceFunc,$total,&$i,$val){
return resolve($val)->then(function($value)use($reduceFunc,$total,&$i,$c){
return$reduceFunc($c,$value,$i++,$total);
});
});
};
$cancellationQueue->enqueue($initialValue);
array_reduce($array,$wrappedReduceFunc,resolve($initialValue))->done($resolve,$reject,$notify);
},$reject,$notify);
},$cancellationQueue);
}
function _checkTypehint(callable$callback,$object){
if(!is_object($object)){
return true;
}
if(is_array($callback)){
$callbackReflection=new\ReflectionMethod($callback[0],$callback[1]);
}elseif(is_object($callback)&&!$callback instanceof\Closure){
$callbackReflection=new\ReflectionMethod($callback,'__invoke');
}else{
$callbackReflection=new\ReflectionFunction($callback);
}
$parameters=$callbackReflection->getParameters();
if(!isset($parameters[0])){
return true;
}
$expectedException=$parameters[0];
if(!$expectedException->getClass()){
return true;
}
return$expectedException->getClass()->isInstance($object);
}
namespace React\Promise\Timer;
use React\Promise\CancellablePromiseInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Promise\Promise;
function timeout(PromiseInterface$promise,$time,LoopInterface$loop){
$canceller=null;
if($promise instanceof CancellablePromiseInterface){
$canceller=function()use(&$promise){
$promise->cancel();
$promise=null;
};
}
return new Promise(function($resolve,$reject)use($loop,$time,$promise){
$timer=null;
$promise=$promise->then(function($v)use(&$timer,$loop,$resolve){
if($timer){
$loop->cancelTimer($timer);
}
$timer=false;
$resolve($v);
},function($v)use(&$timer,$loop,$reject){
if($timer){
$loop->cancelTimer($timer);
}
$timer=false;
$reject($v);
});
if($timer===false){
return;
}
$timer=$loop->addTimer($time,function()use($time,&$promise,$reject){
$reject(new TimeoutException($time,'Timed out after '.$time.' seconds'));
if($promise instanceof CancellablePromiseInterface){
$promise->cancel();
}
$promise=null;
});
},$canceller);
}
function resolve($time,LoopInterface$loop){
return new Promise(function($resolve)use($loop,$time,&$timer){
$timer=$loop->addTimer($time,function()use($time,$resolve){
$resolve($time);
});
},function()use(&$timer,$loop){
$loop->cancelTimer($timer);
$timer=null;
throw new\RuntimeException('Timer cancelled');
});
}
function reject($time,LoopInterface$loop){
return resolve($time,$loop)->then(function($time){
throw new TimeoutException($time,'Timer expired after '.$time.' seconds');
});
}
namespace RingCentral\Psr7;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
defined('PHP_QUERY_RFC1738')or define('PHP_QUERY_RFC1738',1);
defined('PHP_QUERY_RFC3986')or define('PHP_QUERY_RFC3986',2);
function str(MessageInterface$message){
if($message instanceof RequestInterface){
$msg=trim($message->getMethod().' '.$message->getRequestTarget()).' HTTP/'.$message->getProtocolVersion();
if(!$message->hasHeader('host')){
$msg.="\r\nHost: ".$message->getUri()->getHost();
}
}elseif($message instanceof ResponseInterface){
$msg='HTTP/'.$message->getProtocolVersion().' '.$message->getStatusCode().' '.$message->getReasonPhrase();
}else{
throw new\InvalidArgumentException('Unknown message type');
}
foreach($message->getHeaders()as$name=>$values){
$msg.="\r\n{$name}: ".join(', ',$values);
}
return"{$msg}\r\n\r\n".$message->getBody();
}
function uri_for($uri){
if($uri instanceof UriInterface){
return$uri;
}elseif(is_string($uri)){
return new Uri($uri);
}
throw new\InvalidArgumentException('URI must be a string or UriInterface');
}
function stream_for($resource='',array$options=array()){
switch(gettype($resource)){
case'string':$stream=fopen('php://temp','r+');
if($resource!==''){
fputs($stream,$resource);
fseek($stream,0);
}
return new Stream($stream,$options);
case'resource':return new Stream($resource,$options);
case'object':if($resource instanceof StreamInterface){
return$resource;
}elseif($resource instanceof\Iterator){
return new PumpStream(function()use($resource){
if(!$resource->valid()){
return false;
}
$result=$resource->current();
$resource->next();
return$result;
},$options);
}elseif(method_exists($resource,'__toString')){
return stream_for((string)$resource,$options);
}
break;
case'NULL':return new Stream(fopen('php://temp','r+'),$options);
}
if(is_callable($resource)){
return new PumpStream($resource,$options);
}
throw new\InvalidArgumentException('Invalid resource type: '.gettype($resource));
}
function parse_header($header){
static$trimmed="\"'  \n\t\r";
$params=$matches=array();
foreach(normalize_header($header)as$val){
$part=array();
foreach(preg_split('/;(?=([^"]*"[^"]*")*[^"]*$)/',$val)as$kvp){
if(preg_match_all('/<[^>]+>|[^=]+/',$kvp,$matches)){
$m=$matches[0];
if(isset($m[1])){
$part[trim($m[0],$trimmed)]=trim($m[1],$trimmed);
}else{
$part[]=trim($m[0],$trimmed);
}
}
}
if($part){
$params[]=$part;
}
}
return$params;
}
function normalize_header($header){
if(!is_array($header)){
return array_map('trim',explode(',',$header));
}
$result=array();
foreach($header as$value){
foreach((array)$value as$v){
if(strpos($v,',')===false){
$result[]=$v;
continue;
}
foreach(preg_split('/,(?=([^"]*"[^"]*")*[^"]*$)/',$v)as$vv){
$result[]=trim($vv);
}
}
}
return$result;
}
function modify_request(RequestInterface$request,array$changes){
if(!$changes){
return$request;
}
$headers=$request->getHeaders();
if(!isset($changes['uri'])){
$uri=$request->getUri();
}else{
if($host=$changes['uri']->getHost()){
$changes['set_headers']['Host']=$host;
}
$uri=$changes['uri'];
}
if(!empty($changes['remove_headers'])){
$headers=_caseless_remove($changes['remove_headers'],$headers);
}
if(!empty($changes['set_headers'])){
$headers=_caseless_remove(array_keys($changes['set_headers']),$headers);
$headers=$changes['set_headers']+$headers;
}
if(isset($changes['query'])){
$uri=$uri->withQuery($changes['query']);
}
return new Request(isset($changes['method'])?$changes['method']:$request->getMethod(),$uri,$headers,isset($changes['body'])?$changes['body']:$request->getBody(),isset($changes['version'])?$changes['version']:$request->getProtocolVersion());
}
function rewind_body(MessageInterface$message){
$body=$message->getBody();
if($body->tell()){
$body->rewind();
}
}
function try_fopen($filename,$mode){
$ex=null;
$fargs=func_get_args();
set_error_handler(function()use($filename,$mode,&$ex,$fargs){
$ex=new\RuntimeException(sprintf('Unable to open %s using mode %s: %s',$filename,$mode,$fargs[1]));
});
$handle=fopen($filename,$mode);
restore_error_handler();
if($ex){
throw$ex;
}
return$handle;
}
function copy_to_string(StreamInterface$stream,$maxLen=-1){
$buffer='';
if($maxLen===-1){
while(!$stream->eof()){
$buf=$stream->read(1048576);
if($buf==null){
break;
}
$buffer.=$buf;
}
return$buffer;
}
$len=0;
while(!$stream->eof()&&$len<$maxLen){
$buf=$stream->read($maxLen-$len);
if($buf==null){
break;
}
$buffer.=$buf;
$len=strlen($buffer);
}
return$buffer;
}
function copy_to_stream(StreamInterface$source,StreamInterface$dest,$maxLen=-1
){
if($maxLen===-1){
while(!$source->eof()){
if(!$dest->write($source->read(1048576))){
break;
}
}
return;
}
$bytes=0;
while(!$source->eof()){
$buf=$source->read($maxLen-$bytes);
if(!($len=strlen($buf))){
break;
}
$bytes+=$len;
$dest->write($buf);
if($bytes==$maxLen){
break;
}
}
}
function hash(StreamInterface$stream,$algo,$rawOutput=false
){
$pos=$stream->tell();
if($pos>0){
$stream->rewind();
}
$ctx=hash_init($algo);
while(!$stream->eof()){
hash_update($ctx,$stream->read(1048576));
}
$out=hash_final($ctx,(bool)$rawOutput);
$stream->seek($pos);
return$out;
}
function readline(StreamInterface$stream,$maxLength=null){
$buffer='';
$size=0;
while(!$stream->eof()){
if(null==($byte=$stream->read(1))){
return$buffer;
}
$buffer.=$byte;
if($byte==PHP_EOL||++$size==$maxLength-1){
break;
}
}
return$buffer;
}
function parse_request($message){
$data=_parse_message($message);
$matches=array();
if(!preg_match('/^[a-zA-Z]+\s+([a-zA-Z]+:\/\/|\/).*/',$data['start-line'],$matches)){
throw new\InvalidArgumentException('Invalid request string');
}
$parts=explode(' ',$data['start-line'],3);
$subParts=isset($parts[2])?explode('/',$parts[2]):array();
$version=isset($parts[2])?$subParts[1]:'1.1';
$request=new Request($parts[0],$matches[1]==='/'?_parse_request_uri($parts[1],$data['headers']):$parts[1],$data['headers'],$data['body'],$version
);
return$matches[1]==='/'?$request:$request->withRequestTarget($parts[1]);
}
function parse_server_request($message,array$serverParams=array()){
$request=parse_request($message);
return new ServerRequest($request->getMethod(),$request->getUri(),$request->getHeaders(),$request->getBody(),$request->getProtocolVersion(),$serverParams
);
}
function parse_response($message){
$data=_parse_message($message);
if(!preg_match('/^HTTP\/.* [0-9]{3} .*/',$data['start-line'])){
throw new\InvalidArgumentException('Invalid response string');
}
$parts=explode(' ',$data['start-line'],3);
$subParts=explode('/',$parts[0]);
return new Response($parts[1],$data['headers'],$data['body'],$subParts[1],isset($parts[2])?$parts[2]:null
);
}
function parse_query($str,$urlEncoding=true){
$result=array();
if($str===''){
return$result;
}
if($urlEncoding===true){
$decoder=function($value){
return rawurldecode(str_replace('+',' ',$value));
};
}elseif($urlEncoding==PHP_QUERY_RFC3986){
$decoder='rawurldecode';
}elseif($urlEncoding==PHP_QUERY_RFC1738){
$decoder='urldecode';
}else{
$decoder=function($str){return$str;};
}
foreach(explode('&',$str)as$kvp){
$parts=explode('=',$kvp,2);
$key=$decoder($parts[0]);
$value=isset($parts[1])?$decoder($parts[1]):null;
if(!isset($result[$key])){
$result[$key]=$value;
}else{
if(!is_array($result[$key])){
$result[$key]=array($result[$key]);
}
$result[$key][]=$value;
}
}
return$result;
}
function build_query(array$params,$encoding=PHP_QUERY_RFC3986){
if(!$params){
return'';
}
if($encoding===false){
$encoder=function($str){return$str;};
}elseif($encoding==PHP_QUERY_RFC3986){
$encoder='rawurlencode';
}elseif($encoding==PHP_QUERY_RFC1738){
$encoder='urlencode';
}else{
throw new\InvalidArgumentException('Invalid type');
}
$qs='';
foreach($params as$k=>$v){
$k=$encoder($k);
if(!is_array($v)){
$qs.=$k;
if($v!==null){
$qs.='='.$encoder($v);
}
$qs.='&';
}else{
foreach($v as$vv){
$qs.=$k;
if($vv!==null){
$qs.='='.$encoder($vv);
}
$qs.='&';
}
}
}
return$qs?(string)substr($qs,0,-1):'';
}
function mimetype_from_filename($filename){
return mimetype_from_extension(pathinfo($filename,PATHINFO_EXTENSION));
}
function mimetype_from_extension($extension){
static$mimetypes=array('7z'=>'application/x-7z-compressed','aac'=>'audio/x-aac','ai'=>'application/postscript','aif'=>'audio/x-aiff','asc'=>'text/plain','asf'=>'video/x-ms-asf','atom'=>'application/atom+xml','avi'=>'video/x-msvideo','bmp'=>'image/bmp','bz2'=>'application/x-bzip2','cer'=>'application/pkix-cert','crl'=>'application/pkix-crl','crt'=>'application/x-x509-ca-cert','css'=>'text/css','csv'=>'text/csv','cu'=>'application/cu-seeme','deb'=>'application/x-debian-package','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','dvi'=>'application/x-dvi','eot'=>'application/vnd.ms-fontobject','eps'=>'application/postscript','epub'=>'application/epub+zip','etx'=>'text/x-setext','flac'=>'audio/flac','flv'=>'video/x-flv','gif'=>'image/gif','gz'=>'application/gzip','htm'=>'text/html','html'=>'text/html','ico'=>'image/x-icon','ics'=>'text/calendar','ini'=>'text/plain','iso'=>'application/x-iso9660-image','jar'=>'application/java-archive','jpe'=>'image/jpeg','jpeg'=>'image/jpeg','jpg'=>'image/jpeg','js'=>'text/javascript','json'=>'application/json','latex'=>'application/x-latex','log'=>'text/plain','m4a'=>'audio/mp4','m4v'=>'video/mp4','mid'=>'audio/midi','midi'=>'audio/midi','mov'=>'video/quicktime','mp3'=>'audio/mpeg','mp4'=>'video/mp4','mp4a'=>'audio/mp4','mp4v'=>'video/mp4','mpe'=>'video/mpeg','mpeg'=>'video/mpeg','mpg'=>'video/mpeg','mpg4'=>'video/mp4','oga'=>'audio/ogg','ogg'=>'audio/ogg','ogv'=>'video/ogg','ogx'=>'application/ogg','pbm'=>'image/x-portable-bitmap','pdf'=>'application/pdf','pgm'=>'image/x-portable-graymap','png'=>'image/png','pnm'=>'image/x-portable-anymap','ppm'=>'image/x-portable-pixmap','ppt'=>'application/vnd.ms-powerpoint','pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation','ps'=>'application/postscript','qt'=>'video/quicktime','rar'=>'application/x-rar-compressed','ras'=>'image/x-cmu-raster','rss'=>'application/rss+xml','rtf'=>'application/rtf','sgm'=>'text/sgml','sgml'=>'text/sgml','svg'=>'image/svg+xml','swf'=>'application/x-shockwave-flash','tar'=>'application/x-tar','tif'=>'image/tiff','tiff'=>'image/tiff','torrent'=>'application/x-bittorrent','ttf'=>'application/x-font-ttf','txt'=>'text/plain','wav'=>'audio/x-wav','webm'=>'video/webm','wma'=>'audio/x-ms-wma','wmv'=>'video/x-ms-wmv','woff'=>'application/x-font-woff','wsdl'=>'application/wsdl+xml','xbm'=>'image/x-xbitmap','xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','xml'=>'application/xml','xpm'=>'image/x-xpixmap','xwd'=>'image/x-xwindowdump','yaml'=>'text/yaml','yml'=>'text/yaml','zip'=>'application/zip',);
$extension=strtolower($extension);
return isset($mimetypes[$extension])?$mimetypes[$extension]:null;
}
function _parse_message($message){
if(!$message){
throw new\InvalidArgumentException('Invalid message');
}
$lines=preg_split('/(\\r?\\n)/',$message,-1,PREG_SPLIT_DELIM_CAPTURE);
$result=array('start-line'=>array_shift($lines),'headers'=>array(),'body'=>'');
array_shift($lines);
for($i=0,$totalLines=count($lines);$i<$totalLines;$i+=2){
$line=$lines[$i];
if(empty($line)){
if($i<$totalLines-1){
$result['body']=join('',array_slice($lines,$i+2));
}
break;
}
if(strpos($line,':')){
$parts=explode(':',$line,2);
$key=trim($parts[0]);
$value=isset($parts[1])?trim($parts[1]):'';
$result['headers'][$key][]=$value;
}
}
return$result;
}
function _parse_request_uri($path,array$headers){
$hostKey=array_filter(array_keys($headers),function($k){
return strtolower($k)==='host';
});
if(!$hostKey){
return$path;
}
$host=$headers[reset($hostKey)][0];
$scheme=substr($host,-4)===':443'?'https':'http';
return$scheme.'://'.$host.'/'.ltrim($path,'/');
}
function _caseless_remove($keys,array$data){
$result=array();
foreach($keys as&$key){
$key=strtolower($key);
}
foreach($data as$k=>$v){
if(!in_array(strtolower($k),$keys)){
$result[$k]=$v;
}
}
return$result;
}
namespace React\Promise\Stream;
use Evenement\EventEmitterInterface;
use React\Promise;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
function buffer(ReadableStreamInterface$stream,$maxLength=null){
if(!$stream->isReadable()){
return Promise\resolve('');
}
$buffer='';
$promise=new Promise\Promise(function($resolve,$reject)use($stream,$maxLength,&$buffer,&$bufferer){
$bufferer=function($data)use(&$buffer,$reject,$maxLength){
$buffer.=$data;
if($maxLength!==null&&isset($buffer[$maxLength])){
$reject(new\OverflowException('Buffer exceeded maximum length'));
}
};
$stream->on('data',$bufferer);
$stream->on('error',function($error)use($reject){
$reject(new\RuntimeException('An error occured on the underlying stream while buffering',0,$error));
});
$stream->on('close',function()use($resolve,&$buffer){
$resolve($buffer);
});
},function($_,$reject){
$reject(new\RuntimeException('Cancelled buffering'));
});
return$promise->then(null,function($error)use(&$buffer,$bufferer,$stream){
$buffer='';
$stream->removeListener('data',$bufferer);
throw$error;
});
}
function first(EventEmitterInterface$stream,$event='data'){
if($stream instanceof ReadableStreamInterface){
if(!$stream->isReadable()){
return Promise\reject(new\RuntimeException('Stream already closed'));
}
}elseif($stream instanceof WritableStreamInterface){
if(!$stream->isWritable()){
return Promise\reject(new\RuntimeException('Stream already closed'));
}
}
return new Promise\Promise(function($resolve,$reject)use($stream,$event,&$listener){
$listener=function($data=null)use($stream,$event,&$listener,$resolve){
$stream->removeListener($event,$listener);
$resolve($data);
};
$stream->on($event,$listener);
if($event!=='error'){
$stream->on('error',function($error)use($stream,$event,$listener,$reject){
$stream->removeListener($event,$listener);
$reject(new\RuntimeException('An error occured on the underlying stream while waiting for event',0,$error));
});
}
$stream->on('close',function()use($stream,$event,$listener,$reject){
$stream->removeListener($event,$listener);
$reject(new\RuntimeException('Stream closed'));
});
},function($_,$reject)use($stream,$event,&$listener){
$stream->removeListener($event,$listener);
$reject(new\RuntimeException('Operation cancelled'));
});
}
function all(EventEmitterInterface$stream,$event='data'){
if($stream instanceof ReadableStreamInterface){
if(!$stream->isReadable()){
return Promise\resolve(array());
}
}elseif($stream instanceof WritableStreamInterface){
if(!$stream->isWritable()){
return Promise\resolve(array());
}
}
$buffer=array();
$bufferer=function($data=null)use(&$buffer){
$buffer[]=$data;
};
$stream->on($event,$bufferer);
$promise=new Promise\Promise(function($resolve,$reject)use($stream,&$buffer){
$stream->on('error',function($error)use($reject){
$reject(new\RuntimeException('An error occured on the underlying stream while buffering',0,$error));
});
$stream->on('close',function()use($resolve,&$buffer){
$resolve($buffer);
});
},function($_,$reject){
$reject(new\RuntimeException('Cancelled buffering'));
});
return$promise->then(null,function($error)use(&$buffer,$bufferer,$stream,$event){
$buffer=array();
$stream->removeListener($event,$bufferer);
throw$error;
});
}
function unwrapReadable(PromiseInterface$promise){
return new UnwrapReadableStream($promise);
}
function unwrapWritable(PromiseInterface$promise){
return new UnwrapWritableStream($promise);
}
namespace Clue\Commander;
class NoRouteFoundException extends\UnderflowException
{
}
namespace Clue\Commander\Tokens;
interface TokenInterface
{
function matches(array&$input,array&$output);
function __toString();
}
namespace Clue\Commander;
use Clue\Commander\Tokens\TokenInterface;
use InvalidArgumentException;
class Route implements TokenInterface
{
private$token;
function __construct(TokenInterface$token=null,$handler){
if(!is_callable($handler)){
throw new InvalidArgumentException('Route handler is not a valid callable');
}
$this->token=$token;
$this->handler=$handler;
}
function matches(array&$input,array&$output){
if($this->token===null||$this->token->matches($input,$output)){
if(!$input||(count($input)===1&&reset($input)==='--')){
return true;
}
}
return false;
}
function __toString(){
return(string)$this->token;
}
function __invoke(array$args){
return call_user_func($this->handler,$args);
}
}
namespace Clue\Commander;
use Clue\Commander\Tokens\Tokenizer;
use Exception;
class Router
{
private$routes=array();
private$tokenizer;
function __construct(Tokenizer$tokenizer=null){
if($tokenizer===null){
$tokenizer=new Tokenizer;
}
$this->tokenizer=$tokenizer;
}
function add($route,$handler){
if(trim($route)===''){
$token=null;
}else{
$token=$this->tokenizer->createToken($route);
}
$route=new Route($token,$handler);
$this->routes[]=$route;
return$route;
}
function remove(Route$route){
$id=array_search($route,$this->routes);
if($id===false){
throw new\UnderflowException('Given Route not found');
}
unset($this->routes[$id]);
}
function getRoutes(){
return$this->routes;
}
function execArgv(array$argv=null){
try{
$this->handleArgv($argv);
}catch(NoRouteFoundException$e){
fputs(STDERR,'Usage Error: '.$e->getMessage().PHP_EOL);
die(64);
}catch(Exception$e){
fputs(STDERR,'Program Error: '.$e->getMessage().PHP_EOL);
die(1);
}
}
function handleArgv(array$argv=null){
if($argv===null){
$argv=isset($_SERVER['argv'])?$_SERVER['argv']:array();
}
array_shift($argv);
return$this->handleArgs($argv);
}
function handleArgs(array$args){
foreach($this->routes as$route){
$input=$args;
$output=array();
if($route->matches($input,$output)){
return$route($output);
}
}
throw new NoRouteFoundException('No matching route found');
}
}
namespace Clue\Commander\Tokens;
use InvalidArgumentException;
class AlternativeToken implements TokenInterface
{
private$tokens=array();
function __construct(array$tokens){
foreach($tokens as$token){
if($token instanceof OptionalToken){
throw new InvalidArgumentException('Alternative group must not contain optional tokens');
}elseif(!$token instanceof TokenInterface){
throw new InvalidArgumentException('Alternative group must only contain valid tokens');
}elseif($token instanceof self){
foreach($token->tokens as$token){
$this->tokens[]=$token;
}
}else{
$this->tokens[]=$token;
}
}
if(count($this->tokens)<2){
throw new InvalidArgumentException('Alternative group must contain at least 2 tokens');
}
}
function matches(array&$input,array&$output){
foreach($this->tokens as$token){
if($token->matches($input,$output)){
return true;
}
}
return false;
}
function __toString(){
return join(' | ',$this->tokens);
}
}
namespace Clue\Commander\Tokens;
use InvalidArgumentException;
class ArgumentToken implements TokenInterface
{
private$name;
private$filter;
private$callback;
function __construct($name,$filter=null,$callback=null){
if(!isset($name[0])){
throw new InvalidArgumentException('Empty argument name');
}
if(($filter===null&&$callback!==null)||($callback!==null&&!is_callable($callback))){
throw new InvalidArgumentException('Invalid callback given or no filter name for callback given');
}
$this->name=$name;
$this->filter=$filter;
$this->callback=$callback;
if($callback===null){
$demo='';
$this->validate($demo,false);
}
}
function matches(array&$input,array&$output){
$dd=false;
foreach($input as$key=>$value){
if($this->validate($value,$dd)){
unset($input[$key]);
$output[$this->name]=$value;
return true;
}elseif($value===''||$value[0]!=='-'||$dd){
break;
}elseif($value==='--'){
$dd=true;
}
}
return false;
}
function __toString(){
$ret='<'.$this->name;
if($this->filter!==null){
$ret.=':'.$this->filter;
}
$ret.='>';
return$ret;
}
private function validate(&$value,$dd){
if($this->filter===null){
return($dd||$value===''||$value[0]!=='-');
}elseif($this->callback!==null){
$callback=$this->callback;
$ret=$value;
if(!$callback($ret)){
return false;
}
$value=$ret;
return true;
}elseif($this->filter==='int'||$this->filter==='uint'){
$ret=filter_var($value,FILTER_VALIDATE_INT);
if($ret===false||($this->filter==='uint'&&$ret<0)){
return false;
}
$value=$ret;
return true;
}elseif($this->filter==='float'||$this->filter==='ufloat'){
$ret=filter_var($value,FILTER_VALIDATE_FLOAT);
if($ret===false||($this->filter==='ufloat'&&$ret<0)){
return false;
}
$value=$ret;
return true;
}elseif($this->filter==='bool'){
$ret=filter_var($value,FILTER_VALIDATE_BOOLEAN,array('flags'=>FILTER_NULL_ON_FAILURE));
if($ret===null){
return false;
}
$value=$ret;
return true;
}else{
throw new\InvalidArgumentException('Invalid filter name');
}
}
}
namespace Clue\Commander\Tokens;
use InvalidArgumentException;
class EllipseToken implements TokenInterface
{
private$token;
function __construct(TokenInterface$token){
if(!$token instanceof ArgumentToken&&!$token instanceof OptionToken&&!$token instanceof WordToken){
throw new InvalidArgumentException('Ellipse only for individual words/arguments/options');
}
$this->token=$token;
}
function matches(array&$input,array&$output){
$soutput=$output;
if($this->token->matches($input,$output)){
$all=array();
do{
foreach($output as$name=>$value){
if(!isset($soutput[$name])||$soutput[$name]!==$value){
$all[$name][]=$value;
}
}
$output=$soutput;
}while($this->token->matches($input,$output));
$output=$all+$soutput;
return true;
}
return false;
}
function __toString(){
return$this->token.'...';
}
}
namespace Clue\Commander\Tokens;
use InvalidArgumentException;
class OptionToken implements TokenInterface
{
private$name;
private$placeholder;
private$required;
function __construct($name,TokenInterface$placeholder=null,$required=false){
if(!isset($name[1])||$name[0]!=='-'){
throw new InvalidArgumentException('Option name must start with a dash');
}
if($name[1]!=='-'&&isset($name[3])){
throw new InvalidArgumentException('Short option name must consist of a single character');
}
if($name[1]==='-'&&!isset($name[3])){
throw new InvalidArgumentException('Long option must consist of at least two characters');
}
if($required&&$placeholder===null){
throw new InvalidArgumentException('Requires a placeholder when option value is marked required');
}
$this->name=$name;
$this->placeholder=$placeholder;
$this->required=$required;
}
function matches(array&$input,array&$output){
$len=strlen($this->name);
$foundName=null;
foreach($input as$key=>$value){
if($foundName!==null){
if($this->validate($value)){
unset($input[$foundName]);
unset($input[$key]);
$output[ltrim($this->name,'-')]=$value;
return true;
}elseif(!$this->required){
break;
}else{
$foundName=null;
}
}
if(strpos($value,$this->name)===0){
if($value===$this->name){
if($this->placeholder!==null){
$foundName=$key;
continue;
}
$value=false;
}elseif($this->placeholder!==null&&$value[$len]==='='){
$value=substr($value,$len+1);
}elseif($this->placeholder!==null&&$this->name[1]!=='-'){
$value=substr($value,$len);
}else{
continue;
}
if(!$this->validate($value)){
continue;
}
unset($input[$key]);
$output[ltrim($this->name,'-')]=$value;
return true;
}elseif($value==='--'){
break;
}
}
if($foundName!==null&&!$this->required){
unset($input[$foundName]);
$output[ltrim($this->name,'-')]=false;
return true;
}
return false;
}
function __toString(){
$ret=$this->name;
if($this->placeholder!==null){
if($this->required){
if($this->placeholder instanceof SentenceToken||$this->placeholder instanceof AlternativeToken||$this->placeholder instanceof EllipseToken){
$ret.='=('.$this->placeholder.')';
}else{
$ret.='='.$this->placeholder;
}
}else{
$ret.='[='.$this->placeholder.']';
}
}
return$ret;
}
private function validate(&$value){
if($this->placeholder!==null){
$input=array($value);
$output=array();
if(!$this->placeholder->matches($input,$output)){
return false;
}
if($output){
$temp=reset($output);
if($temp!==false||$value===''||$value[0]!=='-'){
$value=$temp;
}
}
}
return true;
}
}
namespace Clue\Commander\Tokens;
use InvalidArgumentException;
class OptionalToken implements TokenInterface
{
private$token;
function __construct(TokenInterface$token){
if($token instanceof self){
throw new InvalidArgumentException('Nested optional block is superfluous');
}
$this->token=$token;
}
function matches(array&$input,array&$output){
return$this->token->matches($input,$output)||true;
}
function __toString(){
return'['.$this->token.']';
}
}
namespace Clue\Commander\Tokens;
use InvalidArgumentException;
class SentenceToken implements TokenInterface
{
private$tokens=array();
function __construct(array$tokens){
foreach($tokens as$token){
if(!$token instanceof TokenInterface){
throw new InvalidArgumentException('Sentence must only contain valid tokens');
}elseif($token instanceof self){
foreach($token->tokens as$token){
$this->tokens[]=$token;
}
}else{
$this->tokens[]=$token;
}
}
if(count($this->tokens)<2){
throw new InvalidArgumentException('Sentence must contain at least 2 tokens');
}
}
function matches(array&$input,array&$output){
$sinput=$input;
$soutput=$output;
foreach($this->tokens as$token){
if(!$token->matches($input,$output)){
$input=$sinput;
$output=$soutput;
return false;
}
}
return true;
}
function __toString(){
return join(' ',array_map(function(TokenInterface$token){
if($token instanceof AlternativeToken){
return'('.$token.')';
}
return(string)$token;
},$this->tokens));
}
}
namespace Clue\Commander\Tokens;
use InvalidArgumentException;
use Clue\Commander\Filter;
class Tokenizer
{
private$ws=array(' ',"\t","\r","\n",);
private$filters=array();
function addFilter($name,$filter){
$this->filters[$name]=$filter;
}
function createToken($input){
$i=0;
$token=$this->readAlternativeSentenceOrSingle($input,$i);
if(isset($input[$i])){
throw new\InvalidArgumentException('Invalid root token, expression has superfluous contents');
}
return$token;
}
private function readSentenceOrSingle($input,&$i){
$tokens=array();
while(true){
$previous=$i;
$this->consumeOptionalWhitespace($input,$i);
if(!isset($input[$i])||strpos('])|',$input[$i])!==false){
break;
}
if($previous===$i&&$tokens){
throw new InvalidArgumentException('Missing whitespace between tokens');
}
$tokens[]=$this->readToken($input,$i);
}
if(isset($tokens[0])&&!isset($tokens[1])){
return$tokens[0];
}
return new SentenceToken($tokens);
}
private function consumeOptionalWhitespace($input,&$i){
for(;isset($input[$i])&&in_array($input[$i],$this->ws);++$i);
}
private function readToken($input,&$i,$readEllipses=true){
if($input[$i]==='<'){
$token=$this->readArgument($input,$i);
}elseif($input[$i]==='['){
$token=$this->readOptionalBlock($input,$i);
}elseif($input[$i]==='('){
$token=$this->readParenthesesBlock($input,$i);
}else{
$token=$this->readWord($input,$i);
}
$start=$i;
$this->consumeOptionalWhitespace($input,$start);
if($readEllipses&&substr($input,$start,3)==='...'){
$token=new EllipseToken($token);
$i=$start+3;
}
return$token;
}
private function readArgument($input,&$i){
for($start=$i++;isset($input[$i])&&$input[$i]!=='>';++$i);
if(!isset($input[$i])){
throw new InvalidArgumentException('Missing end of argument');
}
$word=substr($input,$start+1,$i++-$start-1);
$parts=explode(':',$word,2);
$word=trim($parts[0]);
$filter=isset($parts[1])?trim($parts[1]):null;
$callback=null;
if($filter!==null&&isset($this->filters[$filter])){
$callback=$this->filters[$filter];
}
return new ArgumentToken($word,$filter,$callback);
}
private function readOptionalBlock($input,&$i){
$i++;
$token=$this->readAlternativeSentenceOrSingle($input,$i);
if(!isset($input[$i])||$input[$i]!==']'){
throw new InvalidArgumentException('Missing end of optional block');
}
$i++;
return new OptionalToken($token);
}
private function readParenthesesBlock($input,&$i){
$i++;
$token=$this->readAlternativeSentenceOrSingle($input,$i);
if(!isset($input[$i])||$input[$i]!==')'){
throw new InvalidArgumentException('Missing end of alternative block');
}
$i++;
return$token;
}
private function readAlternativeSentenceOrSingle($input,&$i){
$tokens=array();
while(true){
$tokens[]=$this->readSentenceOrSingle($input,$i);
if(!isset($input[$i])||strpos('])',$input[$i])!==false){
break;
}
$i++;
}
if(isset($tokens[0])&&!isset($tokens[1])){
return$tokens[0];
}
return new AlternativeToken($tokens);
}
private function readWord($input,&$i){
preg_match('/[^\[\]\(\)\|\=\.\s]+/',$input,$matches,0,$i);
$word=isset($matches[0])?$matches[0]:'';
$i+=strlen($word);
if(isset($word[0])&&$word[0]==='-'){
$start=$i;
$this->consumeOptionalWhitespace($input,$start);
if(isset($input[$start])&&$input[$start]==='['){
$start++;
$this->consumeOptionalWhitespace($input,$start);
if(isset($input[$start])&&$input[$start]==='='){
$i=$start+1;
$placeholder=$this->readAlternativeSentenceOrSingle($input,$i);
if(!isset($input[$i])||$input[$i]!==']'){
throw new InvalidArgumentException('Missing end of optional option value');
}
$i++;
$required=false;
}else{
$required=false;
$placeholder=null;
}
}elseif(isset($input[$start])&&$input[$start]==='='){
$i=$start+1;
$this->consumeOptionalWhitespace($input,$i);
$placeholder=$this->readToken($input,$i,false);
$required=true;
}else{
$required=false;
$placeholder=null;
}
$token=new OptionToken($word,$placeholder,$required);
}else{
$token=new WordToken($word);
}
return$token;
}
}
namespace Clue\Commander\Tokens;
use InvalidArgumentException;
class WordToken implements TokenInterface
{
private$word;
function __construct($word){
if(!isset($word[0])){
throw new InvalidArgumentException('Word must not be empty');
}
$this->word=$word;
}
function matches(array&$input,array&$output){
foreach($input as$key=>$value){
if($value===$this->word){
unset($input[$key]);
return true;
}elseif($value===''||$value[0]!=='-'){
break;
}
}
return false;
}
function __toString(){
return$this->word;
}
}
namespace React\Socket;
interface ConnectorInterface
{
function connect($uri);
}
namespace Clue\React\HttpProxy;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use RingCentral\Psr7;
use React\Promise;
use React\Promise\Deferred;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;
use React\Socket\FixedUriConnector;
class ProxyConnector implements ConnectorInterface
{
private$connector;
private$proxyUri;
private$proxyAuth='';
function __construct($proxyUrl,ConnectorInterface$connector){
if(preg_match('/^http\+unix:\/\/(.*?@)?(.+?)$/',$proxyUrl,$match)){
$proxyUrl='http://'.$match[1].'localhost';
$connector=new FixedUriConnector('unix://'.$match[2],$connector
);
}
if(strpos($proxyUrl,'://')===false){
$proxyUrl='http://'.$proxyUrl;
}
$parts=parse_url($proxyUrl);
if(!$parts||!isset($parts['scheme'],$parts['host'])||($parts['scheme']!=='http'&&$parts['scheme']!=='https')){
throw new InvalidArgumentException('Invalid proxy URL "'.$proxyUrl.'"');
}
if(!isset($parts['port'])){
$parts['port']=$parts['scheme']==='https'?443:80;
}
$parts['scheme']=$parts['scheme']==='https'?'tls':'tcp';
$this->connector=$connector;
$this->proxyUri=$parts['scheme'].'://'.$parts['host'].':'.$parts['port'];
if(isset($parts['user'])||isset($parts['pass'])){
$this->proxyAuth='Proxy-Authorization: Basic '.base64_encode(rawurldecode($parts['user'].':'.(isset($parts['pass'])?$parts['pass']:'')))."\r\n";
}
}
function connect($uri){
if(strpos($uri,'://')===false){
$uri='tcp://'.$uri;
}
$parts=parse_url($uri);
if(!$parts||!isset($parts['scheme'],$parts['host'],$parts['port'])||$parts['scheme']!=='tcp'){
return Promise\reject(new InvalidArgumentException('Invalid target URI specified'));
}
$host=trim($parts['host'],'[]');
$port=$parts['port'];
$proxyUri=$this->proxyUri;
if(isset($parts['path'])){
$proxyUri.=$parts['path'];
}
$args=array();
if(isset($parts['query'])){
parse_str($parts['query'],$args);
}
if(!isset($args['hostname'])){
$args['hostname']=$parts['host'];
}
$proxyUri.='?'.http_build_query($args,'','&');;
if(isset($parts['fragment'])){
$proxyUri.='#'.$parts['fragment'];
}
$auth=$this->proxyAuth;
return$this->connector->connect($proxyUri)->then(function(ConnectionInterface$stream)use($host,$port,$auth){
$deferred=new Deferred(function($_,$reject)use($stream){
$reject(new RuntimeException('Connection canceled while waiting for response from proxy (ECONNABORTED)',defined('SOCKET_ECONNABORTED')?SOCKET_ECONNABORTED:103));
$stream->close();
});
$buffer='';
$fn=function($chunk)use(&$buffer,$deferred,$stream){
$buffer.=$chunk;
$pos=strpos($buffer,"\r\n\r\n");
if($pos!==false){
try{
$response=Psr7\parse_response(substr($buffer,0,$pos));
}catch(Exception$e){
$deferred->reject(new RuntimeException('Invalid response received from proxy (EBADMSG)',defined('SOCKET_EBADMSG')?SOCKET_EBADMSG:71,$e));
$stream->close();
return;
}
if($response->getStatusCode()===407){
$deferred->reject(new RuntimeException('Proxy denied connection due to invalid authentication '.$response->getStatusCode().' ('.$response->getReasonPhrase().') (EACCES)',defined('SOCKET_EACCES')?SOCKET_EACCES:13));
return$stream->close();
}elseif($response->getStatusCode()<200||$response->getStatusCode()>=300){
$deferred->reject(new RuntimeException('Proxy refused connection with HTTP error code '.$response->getStatusCode().' ('.$response->getReasonPhrase().') (ECONNREFUSED)',defined('SOCKET_ECONNREFUSED')?SOCKET_ECONNREFUSED:111));
return$stream->close();
}
$deferred->resolve($stream);
$buffer=(string)substr($buffer,$pos+4);
if($buffer!==''){
$stream->emit('data',array($buffer));
$buffer='';
}
return;
}
if(isset($buffer[8192])){
$deferred->reject(new RuntimeException('Proxy must not send more than 8 KiB of headers (EMSGSIZE)',defined('SOCKET_EMSGSIZE')?SOCKET_EMSGSIZE:90));
$stream->close();
}
};
$stream->on('data',$fn);
$stream->on('error',function(Exception$e)use($deferred){
$deferred->reject(new RuntimeException('Stream error while waiting for response from proxy (EIO)',defined('SOCKET_EIO')?SOCKET_EIO:5,$e));
});
$stream->on('close',function()use($deferred){
$deferred->reject(new RuntimeException('Connection to proxy lost while waiting for response (ECONNRESET)',defined('SOCKET_ECONNRESET')?SOCKET_ECONNRESET:104));
});
$stream->write("CONNECT ".$host.":".$port." HTTP/1.1\r\nHost: ".$host.":".$port."\r\n".$auth."\r\n");
return$deferred->promise()->then(function(ConnectionInterface$stream)use($fn){
$stream->removeListener('data',$fn);
return new Promise\FulfilledPromise($stream);
});
},function(Exception$e)use($proxyUri){
throw new RuntimeException('Unable to connect to proxy (ECONNREFUSED)',defined('SOCKET_ECONNREFUSED')?SOCKET_ECONNREFUSED:111,$e);
});
}
}
namespace Clue\React\Socks;
use React\Promise;
use React\Promise\PromiseInterface;
use React\Promise\Deferred;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;
use React\Socket\FixedUriConnector;
use\Exception;
use\InvalidArgumentException;
use RuntimeException;
class Client implements ConnectorInterface
{
private$connector;
private$socksUri;
private$protocolVersion=null;
private$auth=null;
function __construct($socksUri,ConnectorInterface$connector){
if(preg_match('/^(socks(?:5|4|4a)?)(s|\+unix):\/\/(.*?@)?(.+?)$/',$socksUri,$match)){
$socksUri=$match[1].'://'.$match[3].'localhost';
$connector=new FixedUriConnector(($match[2]==='s'?'tls://':'unix://').$match[4],$connector
);
}
if(strpos($socksUri,'://')===false){
$socksUri='socks://'.$socksUri;
}
$parts=parse_url($socksUri);
if(!$parts||!isset($parts['scheme'],$parts['host'])){
throw new\InvalidArgumentException('Invalid SOCKS server URI "'.$socksUri.'"');
}
if(!isset($parts['port'])){
$parts['port']=1080;
}
if(isset($parts['user'])||isset($parts['pass'])){
if($parts['scheme']==='socks'){
$parts['scheme']='socks5';
}elseif($parts['scheme']!=='socks5'){
throw new InvalidArgumentException('Authentication requires SOCKS5. Consider using protocol version 5 or waive authentication');
}
$parts+=array('user'=>'','pass'=>'');
$this->setAuth(rawurldecode($parts['user']),rawurldecode($parts['pass']));
}
$this->setProtocolVersionFromScheme($parts['scheme']);
$this->socksUri=$parts['host'].':'.$parts['port'];
$this->connector=$connector;
}
private function setProtocolVersionFromScheme($scheme){
if($scheme==='socks'||$scheme==='socks4a'){
$this->protocolVersion='4a';
}elseif($scheme==='socks5'){
$this->protocolVersion='5';
}elseif($scheme==='socks4'){
$this->protocolVersion='4';
}else{
throw new InvalidArgumentException('Invalid protocol version given "'.$scheme.'://"');
}
}
private function setAuth($username,$password){
if(strlen($username)>255||strlen($password)>255){
throw new InvalidArgumentException('Both username and password MUST NOT exceed a length of 255 bytes each');
}
$this->auth=pack('C2',1,strlen($username)).$username.pack('C',strlen($password)).$password;
}
function connect($uri){
if(strpos($uri,'://')===false){
$uri='tcp://'.$uri;
}
$parts=parse_url($uri);
if(!$parts||!isset($parts['scheme'],$parts['host'],$parts['port'])||$parts['scheme']!=='tcp'){
return Promise\reject(new InvalidArgumentException('Invalid target URI specified'));
}
$host=trim($parts['host'],'[]');
$port=$parts['port'];
if($this->protocolVersion==='4'&&false===filter_var($host,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)){
return Promise\reject(new InvalidArgumentException('Requires an IPv4 address for SOCKS4'));
}
if(strlen($host)>255||$port>65535||$port<0||(string)$port!==(string)(int)$port){
return Promise\reject(new InvalidArgumentException('Invalid target specified'));
}
$socksUri=$this->socksUri;
if(isset($parts['path'])){
$socksUri.=$parts['path'];
}
$args=array();
if(isset($parts['query'])){
parse_str($parts['query'],$args);
}
if(!isset($args['hostname'])){
$args['hostname']=$host;
}
$socksUri.='?'.http_build_query($args,'','&');
if(isset($parts['fragment'])){
$socksUri.='#'.$parts['fragment'];
}
$that=$this;
return$this->connector->connect($socksUri)->then(function(ConnectionInterface$stream)use($that,$host,$port){
return$that->handleConnectedSocks($stream,$host,$port);
},function(Exception$e){
throw new RuntimeException('Unable to connect to proxy (ECONNREFUSED)',defined('SOCKET_ECONNREFUSED')?SOCKET_ECONNREFUSED:111,$e);
}
);
}
function handleConnectedSocks(ConnectionInterface$stream,$host,$port){
$deferred=new Deferred(function($_,$reject){
$reject(new RuntimeException('Connection canceled while establishing SOCKS session (ECONNABORTED)',defined('SOCKET_ECONNABORTED')?SOCKET_ECONNABORTED:103));
});
$reader=new StreamReader;
$stream->on('data',array($reader,'write'));
$stream->on('error',$onError=function(Exception$e)use($deferred){
$deferred->reject(new RuntimeException('Stream error while waiting for response from proxy (EIO)',defined('SOCKET_EIO')?SOCKET_EIO:5,$e));
});
$stream->on('close',$onClose=function()use($deferred){
$deferred->reject(new RuntimeException('Connection to proxy lost while waiting for response (ECONNRESET)',defined('SOCKET_ECONNRESET')?SOCKET_ECONNRESET:104));
});
if($this->protocolVersion==='5'){
$promise=$this->handleSocks5($stream,$host,$port,$reader);
}else{
$promise=$this->handleSocks4($stream,$host,$port,$reader);
}
$promise->then(function()use($deferred,$stream){
$deferred->resolve($stream);
},function(Exception$error)use($deferred){
if(!$error instanceof RuntimeException){
$error=new RuntimeException('Invalid response received from proxy (EBADMSG)',defined('SOCKET_EBADMSG')?SOCKET_EBADMSG:71,$error);
}
$deferred->reject($error);
});
return$deferred->promise()->then(function(ConnectionInterface$stream)use($reader,$onError,$onClose){
$stream->removeListener('data',array($reader,'write'));
$stream->removeListener('error',$onError);
$stream->removeListener('close',$onClose);
return$stream;
},function($error)use($stream,$onClose){
$stream->removeListener('close',$onClose);
$stream->close();
throw$error;
}
);
}
private function handleSocks4(ConnectionInterface$stream,$host,$port,StreamReader$reader){
$ip=ip2long($host);
$data=pack('C2nNC',4,1,$port,$ip===false?1:$ip,0);
if($ip===false){
$data.=$host.pack('C',0);
}
$stream->write($data);
return$reader->readBinary(array('null'=>'C','status'=>'C','port'=>'n','ip'=>'N'))->then(function($data){
if($data['null']!==0){
throw new Exception('Invalid SOCKS response');
}
if($data['status']!==90){
throw new RuntimeException('Proxy refused connection with SOCKS error code '.sprintf('0x%02X',$data['status']).' (ECONNREFUSED)',defined('SOCKET_ECONNREFUSED')?SOCKET_ECONNREFUSED:111);
}
});
}
private function handleSocks5(ConnectionInterface$stream,$host,$port,StreamReader$reader){
$data=pack('C',5);
$auth=$this->auth;
if($auth===null){
$data.=pack('C2',1,0);
}else{
$data.=pack('C3',2,2,0);
}
$stream->write($data);
$that=$this;
return$reader->readBinary(array('version'=>'C','method'=>'C'))->then(function($data)use($auth,$stream,$reader){
if($data['version']!==5){
throw new Exception('Version/Protocol mismatch');
}
if($data['method']===2&&$auth!==null){
$stream->write($auth);
return$reader->readBinary(array('version'=>'C','status'=>'C'))->then(function($data){
if($data['version']!==1||$data['status']!==0){
throw new RuntimeException('Username/Password authentication failed (EACCES)',defined('SOCKET_EACCES')?SOCKET_EACCES:13);
}
});
}else if($data['method']!==0){
throw new RuntimeException('No acceptable authentication method found (EACCES)',defined('SOCKET_EACCES')?SOCKET_EACCES:13);
}
})->then(function()use($stream,$reader,$host,$port){
$ip=@inet_pton($host);
$data=pack('C3',5,1,0);
if($ip===false){
$data.=pack('C2',3,strlen($host)).$host;
}else{
$data.=pack('C',(strpos($host,':')===false)?1:4).$ip;
}
$data.=pack('n',$port);
$stream->write($data);
return$reader->readBinary(array('version'=>'C','status'=>'C','null'=>'C','type'=>'C'));
})->then(function($data)use($reader){
if($data['version']!==5||$data['null']!==0){
throw new Exception('Invalid SOCKS response');
}
if($data['status']!==0){
if($data['status']===Server::ERROR_GENERAL){
throw new RuntimeException('SOCKS server reported a general server failure (ECONNREFUSED)',defined('SOCKET_ECONNREFUSED')?SOCKET_ECONNREFUSED:111);
}elseif($data['status']===Server::ERROR_NOT_ALLOWED_BY_RULESET){
throw new RuntimeException('SOCKS server reported connection is not allowed by ruleset (EACCES)',defined('SOCKET_EACCES')?SOCKET_EACCES:13);
}elseif($data['status']===Server::ERROR_NETWORK_UNREACHABLE){
throw new RuntimeException('SOCKS server reported network unreachable (ENETUNREACH)',defined('SOCKET_ENETUNREACH')?SOCKET_ENETUNREACH:101);
}elseif($data['status']===Server::ERROR_HOST_UNREACHABLE){
throw new RuntimeException('SOCKS server reported host unreachable (EHOSTUNREACH)',defined('SOCKET_EHOSTUNREACH')?SOCKET_EHOSTUNREACH:113);
}elseif($data['status']===Server::ERROR_CONNECTION_REFUSED){
throw new RuntimeException('SOCKS server reported connection refused (ECONNREFUSED)',defined('SOCKET_ECONNREFUSED')?SOCKET_ECONNREFUSED:111);
}elseif($data['status']===Server::ERROR_TTL){
throw new RuntimeException('SOCKS server reported TTL/timeout expired (ETIMEDOUT)',defined('SOCKET_ETIMEDOUT')?SOCKET_ETIMEDOUT:110);
}elseif($data['status']===Server::ERROR_COMMAND_UNSUPPORTED){
throw new RuntimeException('SOCKS server does not support the CONNECT command (EPROTO)',defined('SOCKET_EPROTO')?SOCKET_EPROTO:71);
}elseif($data['status']===Server::ERROR_ADDRESS_UNSUPPORTED){
throw new RuntimeException('SOCKS server does not support this address type (EPROTO)',defined('SOCKET_EPROTO')?SOCKET_EPROTO:71);
}
throw new RuntimeException('SOCKS server reported an unassigned error code '.sprintf('0x%02X',$data['status']).' (ECONNREFUSED)',defined('SOCKET_ECONNREFUSED')?SOCKET_ECONNREFUSED:111);
}
if($data['type']===1){
return$reader->readLength(6);
}elseif($data['type']===3){
return$reader->readBinary(array('length'=>'C'))->then(function($data)use($reader){
return$reader->readLength($data['length']+2);
});
}elseif($data['type']===4){
return$reader->readLength(18);
}else{
throw new Exception('Invalid SOCKS reponse: Invalid address type');
}
});
}
}
namespace Evenement;
trait EventEmitterTrait
{
protected$listeners=[];
function on($event,callable$listener){
if(!isset($this->listeners[$event])){
$this->listeners[$event]=[];
}
$this->listeners[$event][]=$listener;
return$this;
}
function once($event,callable$listener){
$onceListener=function()use(&$onceListener,$event,$listener){
$this->removeListener($event,$onceListener);
\call_user_func_array($listener,\func_get_args());
};
$this->on($event,$onceListener);
}
function removeListener($event,callable$listener){
if(isset($this->listeners[$event])){
$index=\array_search($listener,$this->listeners[$event],true);
if(false!==$index){
unset($this->listeners[$event][$index]);
if(\count($this->listeners[$event])===0){
unset($this->listeners[$event]);
}
}
}
}
function removeAllListeners($event=null){
if($event!==null){
unset($this->listeners[$event]);
}else{
$this->listeners=[];
}
}
function listeners($event){
return isset($this->listeners[$event])?$this->listeners[$event]:[];
}
function emit($event,array$arguments=[]){
foreach($this->listeners($event)as$listener){
\call_user_func_array($listener,$arguments);
}
}
}
namespace Evenement;
interface EventEmitterInterface
{
function on($event,callable$listener);
function once($event,callable$listener);
function removeListener($event,callable$listener);
function removeAllListeners($event=null);
function listeners($event);
function emit($event,array$arguments=[]);
}
namespace Evenement;
class EventEmitter implements EventEmitterInterface
{
use EventEmitterTrait;
}
namespace Clue\React\Socks;
use Evenement\EventEmitter;
use React\Socket\ServerInterface;
use React\Promise;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\ConnectorInterface;
use React\Socket\Connector;
use React\Socket\ConnectionInterface;
use React\EventLoop\LoopInterface;
use\UnexpectedValueException;
use\InvalidArgumentException;
use\Exception;
use React\Promise\Timer\TimeoutException;
class Server extends EventEmitter
{
const ERROR_GENERAL=1;
const ERROR_NOT_ALLOWED_BY_RULESET=2;
const ERROR_NETWORK_UNREACHABLE=3;
const ERROR_HOST_UNREACHABLE=4;
const ERROR_CONNECTION_REFUSED=5;
const ERROR_TTL=6;
const ERROR_COMMAND_UNSUPPORTED=7;
const ERROR_ADDRESS_UNSUPPORTED=8;
protected$loop;
private$connector;
private$auth=null;
private$protocolVersion=null;
function __construct(LoopInterface$loop,ServerInterface$serverInterface,ConnectorInterface$connector=null){
if($connector===null){
$connector=new Connector($loop);
}
$this->loop=$loop;
$this->connector=$connector;
$that=$this;
$serverInterface->on('connection',function($connection)use($that){
$that->emit('connection',array($connection));
$that->onConnection($connection);
});
}
function setProtocolVersion($version){
if($version!==null){
$version=(string)$version;
if(!in_array($version,array('4','4a','5'),true)){
throw new InvalidArgumentException('Invalid protocol version given');
}
if($version!=='5'&&$this->auth!==null){
throw new UnexpectedValueException('Unable to change protocol version to anything but SOCKS5 while authentication is used. Consider removing authentication info or sticking to SOCKS5');
}
}
$this->protocolVersion=$version;
}
function setAuth($auth){
if(!is_callable($auth)){
throw new InvalidArgumentException('Given authenticator is not a valid callable');
}
if($this->protocolVersion!==null&&$this->protocolVersion!=='5'){
throw new UnexpectedValueException('Authentication requires SOCKS5. Consider using protocol version 5 or waive authentication');
}
$this->auth=function($username,$password,$remote)use($auth){
$ret=call_user_func($auth,$username,$password,$remote);
if($ret instanceof PromiseInterface){
return$ret;
}
$deferred=new Deferred;
$ret?$deferred->resolve():$deferred->reject();
return$deferred->promise();
};
}
function setAuthArray(array$login){
$this->setAuth(function($username,$password)use($login){
return(isset($login[$username])&&(string)$login[$username]===$password);
});
}
function unsetAuth(){
$this->auth=null;
}
function onConnection(ConnectionInterface$connection){
$that=$this;
$handling=$this->handleSocks($connection)->then(function($remote)use($connection){
$connection->emit('ready',array($remote));
},function($error)use($connection,$that){
if(!($error instanceof\Exception)){
$error=new\Exception($error);
}
$connection->emit('error',array($error));
$that->endConnection($connection);
});
$connection->on('close',function()use($handling){
$handling->cancel();
});
}
function endConnection(ConnectionInterface$stream){
$tid=true;
$loop=$this->loop;
$stream->once('close',function()use(&$tid,$loop){
if($tid===true){
$tid=false;
}else{
$loop->cancelTimer($tid);
}
});
$stream->pause();
$stream->end();
if($tid===true){
$tid=$loop->addTimer(3.0,array($stream,'close'));
}
}
private function handleSocks(ConnectionInterface$stream){
$reader=new StreamReader;
$stream->on('data',array($reader,'write'));
$that=$this;
$that=$this;
$auth=$this->auth;
$protocolVersion=$this->protocolVersion;
if($auth!==null){
$protocolVersion='5';
}
return$reader->readByte()->then(function($version)use($stream,$that,$protocolVersion,$auth,$reader){
if($version===4){
if($protocolVersion==='5'){
throw new UnexpectedValueException('SOCKS4 not allowed due to configuration');
}
return$that->handleSocks4($stream,$protocolVersion,$reader);
}else if($version===5){
if($protocolVersion!==null&&$protocolVersion!=='5'){
throw new UnexpectedValueException('SOCKS5 not allowed due to configuration');
}
return$that->handleSocks5($stream,$auth,$reader);
}
throw new UnexpectedValueException('Unexpected/unknown version number');
});
}
function handleSocks4(ConnectionInterface$stream,$protocolVersion,StreamReader$reader){
$supportsHostname=($protocolVersion===null||$protocolVersion==='4a');
$remote=$stream->getRemoteAddress();
if($remote!==null){
$secure=strpos($remote,'tls://')===0;
if(($pos=strpos($remote,'://'))!==false){
$remote=substr($remote,$pos+3);
}
$remote='socks4'.($secure?'s':'').'://'.$remote;
}
$that=$this;
return$reader->readByteAssert(1)->then(function()use($reader){
return$reader->readBinary(array('port'=>'n','ipLong'=>'N','null'=>'C'));
})->then(function($data)use($reader,$supportsHostname,$remote){
if($data['null']!==0){
throw new Exception('Not a null byte');
}
if($data['ipLong']===0){
throw new Exception('Invalid IP');
}
if($data['port']===0){
throw new Exception('Invalid port');
}
if($data['ipLong']<256&&$supportsHostname){
return$reader->readStringNull()->then(function($string)use($data,$remote){
return array($string,$data['port'],$remote);
});
}else{
$ip=long2ip($data['ipLong']);
return array($ip,$data['port'],$remote);
}
})->then(function($target)use($stream,$that){
return$that->connectTarget($stream,$target)->then(function(ConnectionInterface$remote)use($stream){
$stream->write(pack('C8',0,90,0,0,0,0,0,0));
return$remote;
},function($error)use($stream){
$stream->end(pack('C8',0,91,0,0,0,0,0,0));
throw$error;
});
},function($error){
throw new UnexpectedValueException('SOCKS4 protocol error',0,$error);
});
}
function handleSocks5(ConnectionInterface$stream,$auth=null,StreamReader$reader){
$remote=$stream->getRemoteAddress();
if($remote!==null){
$secure=strpos($remote,'tls://')===0;
if(($pos=strpos($remote,'://'))!==false){
$remote=substr($remote,$pos+3);
}
$remote='socks5'.($secure?'s':'').'://'.$remote;
}
$that=$this;
return$reader->readByte()->then(function($num)use($reader){
return$reader->readLength($num);
})->then(function($methods)use($reader,$stream,$auth,&$remote){
if($auth===null&&strpos($methods,"\x00")!==false){
$stream->write(pack('C2',5,0));
return 0;
}else if($auth!==null&&strpos($methods,"\x02")!==false){
$stream->write(pack('C2',5,2));
return$reader->readByteAssert(1)->then(function()use($reader){
return$reader->readByte();
})->then(function($length)use($reader){
return$reader->readLength($length);
})->then(function($username)use($reader,$auth,$stream,&$remote){
return$reader->readByte()->then(function($length)use($reader){
return$reader->readLength($length);
})->then(function($password)use($username,$auth,$stream,&$remote){
if($remote!==null){
$remote=str_replace('://','://'.rawurlencode($username).':'.rawurlencode($password).'@',$remote);
}
return$auth($username,$password,$remote)->then(function()use($stream,$username){
$stream->emit('auth',array($username));
$stream->write(pack('C2',1,0));
},function()use($stream){
$stream->end(pack('C2',1,255));
throw new UnexpectedValueException('Unable to authenticate');
});
});
});
}else{
$stream->write(pack('C2',5,255));
throw new UnexpectedValueException('No acceptable authentication mechanism found');
}
})->then(function($method)use($reader,$stream){
return$reader->readBinary(array('version'=>'C','command'=>'C','null'=>'C','type'=>'C'));
})->then(function($data)use($reader){
if($data['version']!==5){
throw new UnexpectedValueException('Invalid SOCKS version');
}
if($data['command']!==1){
throw new UnexpectedValueException('Only CONNECT requests supported',Server::ERROR_COMMAND_UNSUPPORTED);
}
if($data['type']===3){
return$reader->readByte()->then(function($len)use($reader){
return$reader->readLength($len);
});
}else if($data['type']===1){
return$reader->readLength(4)->then(function($addr){
return inet_ntop($addr);
});
}else if($data['type']===4){
return$reader->readLength(16)->then(function($addr){
return inet_ntop($addr);
});
}else{
throw new UnexpectedValueException('Invalid address type',Server::ERROR_ADDRESS_UNSUPPORTED);
}
})->then(function($host)use($reader,&$remote){
return$reader->readBinary(array('port'=>'n'))->then(function($data)use($host,&$remote){
return array($host,$data['port'],$remote);
});
})->then(function($target)use($that,$stream){
return$that->connectTarget($stream,$target);
},function($error)use($stream){
throw new UnexpectedValueException('SOCKS5 protocol error',$error->getCode(),$error);
})->then(function(ConnectionInterface$remote)use($stream){
$stream->write(pack('C4Nn',5,0,0,1,0,0));
return$remote;
},function(Exception$error)use($stream){
$stream->write(pack('C4Nn',5,$error->getCode()===0?Server::ERROR_GENERAL:$error->getCode(),0,1,0,0));
throw$error;
});
}
function connectTarget(ConnectionInterface$stream,array$target){
$uri=$target[0];
if(strpos($uri,':')!==false){
$uri='['.$uri.']';
}
$uri.=':'.$target[1];
$parts=parse_url('tcp://'.$uri);
if(!$parts||!isset($parts['scheme'],$parts['host'],$parts['port'])||count($parts)!==3){
return Promise\reject(new InvalidArgumentException('Invalid target URI given'));
}
if(isset($target[2])){
$uri.='?source='.rawurlencode($target[2]);
}
$stream->emit('target',$target);
$that=$this;
$connecting=$this->connector->connect($uri);
$stream->on('close',function()use($connecting){
$connecting->cancel();
});
return$connecting->then(function(ConnectionInterface$remote)use($stream,$that){
$stream->pipe($remote,array('end'=>false));
$remote->pipe($stream,array('end'=>false));
$remote->on('end',function()use($stream,$that){
$stream->emit('shutdown',array('remote',null));
$that->endConnection($stream);
});
$stream->on('end',function()use($remote,$that){
$that->endConnection($remote);
});
$stream->bufferSize=$remote->bufferSize=100*1024*1024;
return$remote;
},function(Exception$error){
$code=Server::ERROR_GENERAL;
if((defined('SOCKET_EACCES')&&$error->getCode()===SOCKET_EACCES)||$error->getCode()===13){
$code=Server::ERROR_NOT_ALLOWED_BY_RULESET;
}elseif((defined('SOCKET_EHOSTUNREACH')&&$error->getCode()===SOCKET_EHOSTUNREACH)||$error->getCode()===113){
$code=Server::ERROR_HOST_UNREACHABLE;
}elseif((defined('SOCKET_ENETUNREACH')&&$error->getCode()===SOCKET_ENETUNREACH)||$error->getCode()===101){
$code=Server::ERROR_NETWORK_UNREACHABLE;
}elseif((defined('SOCKET_ECONNREFUSED')&&$error->getCode()===SOCKET_ECONNREFUSED)||$error->getCode()===111||$error->getMessage()==='Connection refused'){
$code=Server::ERROR_CONNECTION_REFUSED;
}elseif((defined('SOCKET_ETIMEDOUT')&&$error->getCode()===SOCKET_ETIMEDOUT)||$error->getCode()===110||$error instanceof TimeoutException){
$code=Server::ERROR_TTL;
}
throw new UnexpectedValueException('Unable to connect to remote target',$code,$error);
});
}
}
namespace Clue\React\Socks;
use React\Promise\Deferred;
use\InvalidArgumentException;
use\UnexpectedValueException;
class StreamReader
{
const RET_DONE=true;
const RET_INCOMPLETE=null;
private$buffer='';
private$queue=array();
function write($data){
$this->buffer.=$data;
do{
$current=reset($this->queue);
if($current===false){
break;
}
$ret=$current($this->buffer);
if($ret===self::RET_INCOMPLETE){
break;
}else{
array_shift($this->queue);
}
}while(true);
}
function readBinary($structure){
$length=0;
$unpack='';
foreach($structure as$name=>$format){
if($length!==0){
$unpack.='/';
}
$unpack.=$format.$name;
if($format==='C'){
++$length;
}else if($format==='n'){
$length+=2;
}else if($format==='N'){
$length+=4;
}else{
throw new InvalidArgumentException('Invalid format given');
}
}
return$this->readLength($length)->then(function($response)use($unpack){
return unpack($unpack,$response);
});
}
function readLength($bytes){
$deferred=new Deferred;
$this->readBufferCallback(function(&$buffer)use($bytes,$deferred){
if(strlen($buffer)>=$bytes){
$deferred->resolve((string)substr($buffer,0,$bytes));
$buffer=(string)substr($buffer,$bytes);
return StreamReader::RET_DONE;
}
});
return$deferred->promise();
}
function readByte(){
return$this->readBinary(array('byte'=>'C'))->then(function($data){
return$data['byte'];
});
}
function readByteAssert($expect){
return$this->readByte()->then(function($byte)use($expect){
if($byte!==$expect){
throw new UnexpectedValueException('Unexpected byte encountered');
}
return$byte;
});
}
function readStringNull(){
$deferred=new Deferred;
$string='';
$that=$this;
$readOne=function()use(&$readOne,$that,$deferred,&$string){
$that->readByte()->then(function($byte)use($deferred,&$string,$readOne){
if($byte===0){
$deferred->resolve($string);
}else{
$string.=chr($byte);
$readOne();
}
});
};
$readOne();
return$deferred->promise();
}
function readBufferCallback($callable){
if(!is_callable($callable)){
throw new InvalidArgumentException('Given function must be callable');
}
if($this->queue){
$this->queue[]=$callable;
}else{
$this->queue=array($callable);
if($this->buffer!==''){
$this->write('');
}
}
}
function getBuffer(){
return$this->buffer;
}
}
namespace ConnectionManager\Extra;
use React\Socket\ConnectorInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Timer;
class ConnectionManagerDelay implements ConnectorInterface
{
private$connectionManager;
private$delay;
private$loop;
function __construct(ConnectorInterface$connectionManager,$delay,LoopInterface$loop){
$this->connectionManager=$connectionManager;
$this->delay=$delay;
$this->loop=$loop;
}
function connect($uri){
$connectionManager=$this->connectionManager;
return Timer\resolve($this->delay,$this->loop)->then(function()use($connectionManager,$uri){
return$connectionManager->connect($uri);
});
}
}
namespace ConnectionManager\Extra;
use React\Socket\ConnectorInterface;
use React\Promise;
use Exception;
class ConnectionManagerReject implements ConnectorInterface
{
private$reason='Connection rejected';
function __construct($reason=null){
if($reason!==null){
$this->reason=$reason;
}
}
function connect($uri){
$reason=$this->reason;
if(!is_string($reason)){
try{
$reason=$reason($uri);
}catch(\Exception$e){
$reason=$e;
}
}
if(!$reason instanceof\Exception){
$reason=new Exception($reason);
}
return Promise\reject($reason);
}
}
namespace ConnectionManager\Extra;
use React\Socket\ConnectorInterface;
use InvalidArgumentException;
use Exception;
use React\Promise\Promise;
use React\Promise\CancellablePromiseInterface;
class ConnectionManagerRepeat implements ConnectorInterface
{
protected$connectionManager;
protected$maximumTries;
function __construct(ConnectorInterface$connectionManager,$maximumTries){
if($maximumTries<1){
throw new InvalidArgumentException('Maximum number of tries must be >= 1');
}
$this->connectionManager=$connectionManager;
$this->maximumTries=$maximumTries;
}
function connect($uri){
$tries=$this->maximumTries;
$connector=$this->connectionManager;
return new Promise(function($resolve,$reject)use($uri,&$pending,&$tries,$connector){
$try=function($error=null)use(&$try,&$pending,&$tries,$uri,$connector,$resolve,$reject){
if($tries>0){
--$tries;
$pending=$connector->connect($uri);
$pending->then($resolve,$try);
}else{
$reject(new Exception('Connection still fails even after retrying',0,$error));
}
};
$try();
},function($_,$reject)use(&$pending,&$tries){
$tries=0;
$reject(new\RuntimeException('Cancelled'));
if($pending instanceof CancellablePromiseInterface){
$pending->cancel();
}
});
}
}
namespace ConnectionManager\Extra;
use React\Socket\ConnectorInterface;
class ConnectionManagerSwappable implements ConnectorInterface
{
protected$connectionManager;
function __construct(ConnectorInterface$connectionManager){
$this->connectionManager=$connectionManager;
}
function connect($uri){
return$this->connectionManager->connect($uri);
}
function setConnectionManager(ConnectorInterface$connectionManager){
$this->connectionManager=$connectionManager;
}
}
namespace ConnectionManager\Extra;
use React\Socket\ConnectorInterface;
use React\EventLoop\LoopInterface;
use React\Promise\Timer;
class ConnectionManagerTimeout implements ConnectorInterface
{
private$connectionManager;
private$timeout;
private$loop;
function __construct(ConnectorInterface$connectionManager,$timeout,LoopInterface$loop){
$this->connectionManager=$connectionManager;
$this->timeout=$timeout;
$this->loop=$loop;
}
function connect($uri){
$promise=$this->connectionManager->connect($uri);
return Timer\timeout($promise,$this->timeout,$this->loop)->then(null,function($e)use($promise){
$promise->then(function($connection){
$connection->end();
});
throw$e;
});
}
}
namespace ConnectionManager\Extra\Multiple;
use React\Socket\ConnectorInterface;
use React\Promise;
use UnderflowException;
use React\Promise\CancellablePromiseInterface;
class ConnectionManagerConsecutive implements ConnectorInterface
{
protected$managers;
function __construct(array$managers){
if(!$managers){
throw new\InvalidArgumentException('List of connectors must not be empty');
}
$this->managers=$managers;
}
function connect($uri){
return$this->tryConnection($this->managers,$uri);
}
function tryConnection(array$managers,$uri){
return new Promise\Promise(function($resolve,$reject)use(&$managers,&$pending,$uri){
$try=function()use(&$try,&$managers,$uri,$resolve,$reject,&$pending){
if(!$managers){
return$reject(new UnderflowException('No more managers to try to connect through'));
}
$manager=array_shift($managers);
$pending=$manager->connect($uri);
$pending->then($resolve,$try);
};
$try();
},function($_,$reject)use(&$managers,&$pending){
$managers=array();
$reject(new\RuntimeException('Cancelled'));
if($pending instanceof CancellablePromiseInterface){
$pending->cancel();
}
});
}
}
namespace ConnectionManager\Extra\Multiple;
use ConnectionManager\Extra\Multiple\ConnectionManagerConsecutive;
use React\Promise;
use React\Promise\CancellablePromiseInterface;
class ConnectionManagerConcurrent extends ConnectionManagerConsecutive
{
function connect($uri){
$all=array();
foreach($this->managers as$connector){
$all[]=$connector->connect($uri);
}
return Promise\any($all)->then(function($conn)use($all){
foreach($all as$promise){
if($promise instanceof CancellablePromiseInterface){
$promise->cancel();
}
$promise->then(function($stream)use($conn){
if($stream!==$conn){
$stream->close();
}
});
}
return$conn;
});
}
}
namespace ConnectionManager\Extra\Multiple;
class ConnectionManagerRandom extends ConnectionManagerConsecutive
{
function connect($uri){
$managers=$this->managers;
shuffle($managers);
return$this->tryConnection($managers,$uri);
}
}
namespace ConnectionManager\Extra\Multiple;
use React\Socket\ConnectorInterface;
use React\Promise;
use UnderflowException;
use InvalidArgumentException;
class ConnectionManagerSelective implements ConnectorInterface
{
private$managers;
function __construct(array$managers){
foreach($managers as$filter=>$manager){
$host=$filter;
$portMin=0;
$portMax=65535;
$colon=strrpos($host,':');
if($colon!==false&&(strpos($host,':')===$colon||substr($host,$colon-1,1)===']')){
if(!isset($host[$colon+1])){
throw new InvalidArgumentException('Entry "'.$filter.'" has no port after colon');
}
$minus=strpos($host,'-',$colon);
if($minus===false){
$portMin=$portMax=(int)substr($host,$colon+1);
if(substr($host,$colon+1)!==(string)$portMin){
throw new InvalidArgumentException('Entry "'.$filter.'" has no valid port after colon');
}
}else{
$portMin=(int)substr($host,$colon+1,($minus-$colon));
$portMax=(int)substr($host,$minus+1);
if(substr($host,$colon+1)!==($portMin.'-'.$portMax)){
throw new InvalidArgumentException('Entry "'.$filter.'" has no valid port range after colon');
}
if($portMin>$portMax){
throw new InvalidArgumentException('Entry "'.$filter.'" has port range mixed up');
}
}
$host=substr($host,0,$colon);
}
if($host===''){
throw new InvalidArgumentException('Entry "'.$filter.'" has an empty host');
}
if(!$manager instanceof ConnectorInterface){
throw new InvalidArgumentException('Entry "'.$filter.'" is not a valid connector');
}
}
$this->managers=$managers;
}
function connect($uri){
$parts=parse_url((strpos($uri,'://')===false?'tcp://':'').$uri);
if(!isset($parts)||!isset($parts['scheme'],$parts['host'],$parts['port'])){
return Promise\reject(new InvalidArgumentException('Invalid URI'));
}
$connector=$this->getConnectorForTarget(trim($parts['host'],'[]'),$parts['port']);
if($connector===null){
return Promise\reject(new UnderflowException('No connector for given target found'));
}
return$connector->connect($uri);
}
private function getConnectorForTarget($targetHost,$targetPort){
foreach($this->managers as$host=>$connector){
$portMin=0;
$portMax=65535;
$colon=strrpos($host,':');
if($colon!==false&&(strpos($host,':')===$colon||substr($host,$colon-1,1)===']')){
$minus=strpos($host,'-',$colon);
if($minus===false){
$portMin=$portMax=(int)substr($host,$colon+1);
}else{
$portMin=(int)substr($host,$colon+1,($minus-$colon));
$portMax=(int)substr($host,$minus+1);
}
$host=trim(substr($host,0,$colon),'[]');
}
if($targetPort>=$portMin&&$targetPort<=$portMax&&fnmatch($host,$targetHost)){
return$connector;
}
}
return;
}
}
namespace LeProxy\LeProxy;
use Clue\React\HttpProxy\ProxyConnector as HttpClient;
use Clue\React\Socks\Client as SocksClient;
use React\EventLoop\LoopInterface;
use React\Socket\Connector;
use React\Socket\ConnectorInterface;
use ConnectionManager\Extra\ConnectionManagerReject;
use ConnectionManager\Extra\Multiple\ConnectionManagerSelective;
class ConnectorFactory
{
const CODE_BLOCKED=4711;
static function coerceProxyUri($uri){
if($uri===''){
throw new\InvalidArgumentException('Upstream proxy URI must not be empty');
}
if(preg_match('/^(?:(?<scheme>socks(?:5|4|4a)?|http)\+unix:\/\/)?(?<auth>[^@]*@)?(?<path>.?.?\/.*)$/',$uri,$match)){
if($match['scheme']===''){
$match['scheme']='http';
}
if($match['auth']!==''){
if($match['scheme']==='socks'){
$match['scheme']='socks5';
}
if($match['scheme']!=='http'&&$match['scheme']!=='socks5'){
throw new\InvalidArgumentException('Upstream proxy scheme "'.$match['scheme'].'+unix://" does not support username/password authentication');
}
}
return$match['scheme'].'+unix://'.(isset($match['auth'])?$match['auth']:'').$match['path'];
}
if(strpos($uri,'://')===false){
$uri='http://'.$uri;
}
$parts=parse_url($uri);
if(!$parts||!isset($parts['scheme'],$parts['host'])||isset($parts['path'])||isset($parts['query'])||isset($parts['fragment'])){
throw new\InvalidArgumentException('Upstream proxy "'.$uri.'" can not be parsed as a valid URI');
}
if(!in_array($parts['scheme'],array('http','socks','socks5','socks4','socks4a'))){
throw new\InvalidArgumentException('Upstream proxy scheme "'.$parts['scheme'].'://" not supported');
}
if(!isset($parts['port'])){
$parts['port']=8080;
}
if(isset($parts['user'])||isset($parts['pass'])){
if($parts['scheme']==='socks'){
$parts['scheme']='socks5';
}
if($parts['scheme']!=='http'&&$parts['scheme']!=='socks5'){
throw new\InvalidArgumentException('Upstream proxy scheme "'.$parts['scheme'].'://" does not support username/password authentication');
}
$parts+=array('user'=>'','pass'=>'');
$parts['host']=$parts['user'].':'.$parts['pass'].'@'.$parts['host'];
}
return$parts['scheme'].'://'.$parts['host'].':'.$parts['port'];
}
static function coerceListenUri($uri){
if(preg_match('/^(?:[^@]*@)?.?.?\/.*$/',$uri)){
return$uri;
}
$original=$uri;
$uri=preg_replace('/(^|@)(:\d+)?$/','${1}0.0.0.0${2}',$uri);
$nullport=false;
if(substr($uri,-2)===':0'){
$nullport=true;
$uri=(string)substr($uri,0,-2);
}
$parts=parse_url('http://'.$uri);
if(!$parts||!isset($parts['scheme'],$parts['host'])||isset($parts['path'])||isset($parts['query'])||isset($parts['fragment'])){
throw new\InvalidArgumentException('Listening URI "'.$original.'" can not be parsed as a valid URI');
}
if(false===filter_var(trim($parts['host'],'[]'),FILTER_VALIDATE_IP)){
throw new\InvalidArgumentException('Listening URI "'.$original.'" must contain a valid IP, not a hostname');
}
if($nullport){
$uri.=':0';
}elseif(!isset($parts['port'])){
$uri.=':8080';
}
return$uri;
}
static function isIpLocal($ip){
return(strpos($ip,'127.')===0||strpos($ip,'::ffff:127.')===0||$ip==='::1');
}
static function createConnectorChain(array$path,LoopInterface$loop){
$connector=new Connector($loop,array('timeout'=>false
));
foreach($path as$proxy){
if(strpos($proxy,'://')===false||strpos($proxy,'http://')===0||strpos($proxy,'http+unix://')===0){
$connector=new HttpClient($proxy,$connector);
}else{
$connector=new SocksClient($proxy,$connector);
}
}
return new Connector($loop,array('tcp'=>$connector,'dns'=>false
));
}
static function coerceBlockUri($uri){
if(isset($uri[0])&&$uri[0]===':'){
$uri='*'.$uri;
}
$excess=$parts=parse_url('tcp://'.$uri);
unset($excess['scheme'],$excess['host'],$excess['port']);
if(!$parts||!isset($parts['scheme'],$parts['host'])||$excess){
throw new\InvalidArgumentException('Invalid block address');
}
return$parts['host'].(isset($parts['port'])?(':'.$parts['port']):'');
}
static function createBlockingConnector(array$block,ConnectorInterface$base){
$reject=new ConnectionManagerReject(function(){
throw new\RuntimeException('Connection blocked',self::CODE_BLOCKED);
});
$filter=array();
foreach($block as$host){
$filter[$host]=$reject;
if(substr($host,0,1)!=='*'){
$filter['*.'.$host]=$reject;
}
}
if(!isset($filter['*'])){
$filter['*']=$base;
}
return new ConnectionManagerSelective($filter);
}
static function filterRootDomains($domains){
$keep=array_fill_keys($domains,true);
foreach($domains as$domain){
$search=$domain;
while(($pos=strpos($search,'.'))!==false){
$search=substr($search,$pos+1);
if(isset($keep[$search])){
unset($keep[$domain]);
break;
}
}
}
return array_keys($keep);
}
}
namespace LeProxy\LeProxy;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\LoopInterface;
use React\Http\Response;
use React\Http\StreamingServer as HttpServer;
use React\HttpClient\Client as HttpClient;
use React\HttpClient\Response as ClientResponse;
use React\Promise\Deferred;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;
use React\Socket\ServerInterface;
use Exception;
use React\Stream\ReadableStreamInterface;
use React\Promise\Timer\TimeoutException;
class HttpProxyServer
{
private$connector;
private$client;
private$auth=null;
private$headers=array('Server'=>'LeProxy','X-Powered-By'=>'');
var$allowUnprotected=true;
function __construct(LoopInterface$loop,ServerInterface$socket,ConnectorInterface$connector,HttpClient$client=null){
if($client===null){
$client=new HttpClient($loop,$connector);
}
$this->connector=$connector;
$this->client=$client;
$that=$this;
$server=new HttpServer(array($this,'handleRequest'));
$server->listen($socket);
}
function setAuthArray(array$auth){
$this->auth=$auth;
}
function handleRequest(ServerRequestInterface$request){
$params=$request->getServerParams();
if(isset($params['REMOTE_ADDR'],$params['REMOTE_PORT'])){
$request=$request->withAttribute('source','http://'.$params['REMOTE_ADDR'].':'.$params['REMOTE_PORT']);
}
if($request->hasHeader('Transfer-Encoding')){
return new Response(411,array('Content-Type'=>'text/plain',)+$this->headers,'LeProxy HTTP/SOCKS proxy does not allow buffering chunked requests');
}
$direct=substr($request->getRequestTarget(),0,1)==='/';
if($direct&&$request->getUri()->getPath()==='/pac'){
return$this->handlePac($request);
}
if($this->auth!==null){
$auth=null;
$value=$request->getHeaderLine('Proxy-Authorization');
if(strpos($value,'Basic ')===0){
$value=base64_decode(substr($value,6),true);
if($value!==false){
$auth=explode(':',$value,2)+array(1=>'');
}
}
if(!$auth||!isset($this->auth[$auth[0]])||$this->auth[$auth[0]]!==$auth[1]){
return new Response(407,array('Proxy-Authenticate'=>'Basic realm="LeProxy HTTP/SOCKS proxy"','Content-Type'=>'text/plain')+$this->headers,'LeProxy HTTP/SOCKS proxy: Valid proxy authentication required');
}
$source=$request->getAttribute('source');
if($source!==null){
$request=$request->withAttribute('source',str_replace('://','://'.rawurlencode($auth[0]).':'.rawurlencode($auth[1]).'@',$source
));
}
}elseif(!$this->allowUnprotected){
$params=$request->getServerParams();
if(isset($params['REMOTE_ADDR'])&&!ConnectorFactory::isIpLocal(trim($params['REMOTE_ADDR'],'[]'))){
return new Response(403,array('Content-Type'=>'text/plain')+$this->headers,'LeProxy HTTP/SOCKS proxy is running in protected mode and allows local access only');
}
}
if(strpos($request->getRequestTarget(),'://')!==false){
return$this->handlePlainRequest($request);
}
if($request->getMethod()==='CONNECT'){
return$this->handleConnectRequest($request);
}
return new Response(405,array('Content-Type'=>'text/plain','Allow'=>'CONNECT')+$this->headers,'LeProxy HTTP/SOCKS proxy');
}
function handleConnectRequest(ServerRequestInterface$request){
$uri=$request->getRequestTarget();
$source=$request->getAttribute('source');
if($source!==null){
$uri.='?source='.rawurlencode($source);
}
return$this->connector->connect($uri)->then(function(ConnectionInterface$remote){
return new Response(200,$this->headers,$remote
);
},function(\Exception$e){
return new Response($this->getCode($e),array('Content-Type'=>'text/plain')+$this->headers,'Unable to connect: '.$this->getMessage($e));
}
);
}
function handlePlainRequest(ServerRequestInterface$request){
$incoming=$request->withoutHeader('Host')->withoutHeader('Connection')->withoutHeader('Proxy-Authorization')->withoutHeader('Proxy-Connection');
$headers=$incoming->getHeaders();
if(!$request->hasHeader('User-Agent')){
$headers['User-Agent']=array();
}
$source=$request->getAttribute('source');
if($source!==null){
$connector=new SourceConnector($this->connector,$source);
$ref=new\ReflectionObject($this->client);
$ref=$ref->getProperty('connector');
$ref->setAccessible(true);
$ref->setValue($this->client,$connector);
}
$outgoing=$this->client->request($incoming->getMethod(),(string)$incoming->getUri(),$headers,$incoming->getProtocolVersion());
$deferred=new Deferred(function()use($outgoing){
$outgoing->close();
throw new\RuntimeException('Request cancelled');
});
$outgoing->on('response',function(ClientResponse$response)use($deferred){
$response=new Response($response->getCode(),$response->getHeaders(),$response,$response->getVersion(),$response->getReasonPhrase());
foreach(array('X-Powered-By','Date')as$header){
if(!$response->hasHeader($header)){
$response=$response->withHeader($header,'');
}
}
$deferred->resolve($response);
});
$outgoing->on('error',function(Exception$e)use($deferred){
$deferred->resolve(new Response($this->getCode($e),array('Content-Type'=>'text/plain')+$this->headers,'Unable to request: '.$this->getMessage($e)));
});
$body=$incoming->getBody();
if($body instanceof ReadableStreamInterface){
$body->pipe($outgoing);
}else{
$outgoing->end((string)$body);
}
return$deferred->promise();
}
function handlePac(ServerRequestInterface$request){
if($request->getMethod()!=='GET'&&$request->getMethod()!=='HEAD'){
return new Response(405,array('Accept'=>'GET')+$this->headers
);
}
$uri=$request->getUri()->getHost().':'.($request->getUri()->getPort()!==null?$request->getUri()->getPort():80);
return new Response(200,array('Content-Type'=>'application/x-ns-proxy-autoconfig',)+$this->headers,<<<EOF
function FindProxyForURL(url, host) {
    if (isPlainHostName(host) ||
        shExpMatch(host, "*.local") ||
        shExpMatch(host, "*.localhost") ||
        isInNet(dnsResolve(host), "10.0.0.0", "255.0.0.0") ||
        isInNet(dnsResolve(host), "172.16.0.0", "255.240.0.0") ||
        isInNet(dnsResolve(host), "192.168.0.0", "255.255.0.0") ||
        isInNet(dnsResolve(host), "127.0.0.0", "255.0.0.0")
    ) {
        return "DIRECT";
    }

    return "PROXY $uri";
}

EOF
);
}
private function getCode(\Exception$e){
if($e->getCode()===ConnectorFactory::CODE_BLOCKED){
return 403;
}elseif($e instanceof TimeoutException){
return 504;
}
return 502;}
private function getMessage(Exception$e){
$message='';
while($e!==null){
$message.=$e->getMessage()."\n";
$e=$e->getPrevious();
}
return$message;
}
}
namespace LeProxy\LeProxy;
use Clue\React\Socks\Server as SocksServer;
use React\EventLoop\LoopInterface;
use React\Socket\Connector;
use React\Socket\ConnectorInterface;
use React\Socket\Server as Socket;
use InvalidArgumentException;
use React\Socket\ConnectionInterface;
class LeProxyServer
{
private$connector;
private$loop;
function __construct(LoopInterface$loop,ConnectorInterface$connector=null){
if($connector===null){
$connector=new Connector($loop);
}
$this->connector=$connector;
$this->loop=$loop;
}
function listen($listen,$allowUnprotected){
if(preg_match('/^(([^:]*):([^@]*)@)?(.?.?\/.*)$/',$listen,$parts)){
$socket=new Socket('unix://'.$parts[4],$this->loop);
$parts=isset($parts[1])&&$parts[1]!==''?array('user'=>$parts[2],'pass'=>$parts[3]):array();
}else{
$nullport=false;
if(substr($listen,-2)===':0'){
$nullport=true;
$listen=substr($listen,0,-2).':10000';
}
$parts=parse_url('http://'.$listen);
if(!$parts||!isset($parts['scheme'],$parts['host'],$parts['port'])){
throw new InvalidArgumentException('Invalid URI for listening address');
}
if($nullport){
$parts['port']=0;
}
$socket=new Socket($parts['host'].':'.$parts['port'],$this->loop);
}
$unification=new ProtocolDetector($socket);
$http=new HttpProxyServer($this->loop,$unification->http,$this->connector);
$socks=new SocksServer($this->loop,$unification->socks,new SocksErrorConnector($this->connector));
if(isset($parts['user'])||isset($parts['pass'])){
$auth=array(rawurldecode($parts['user'])=>isset($parts['pass'])?rawurldecode($parts['pass']):'');
$http->setAuthArray($auth);
$socks->setAuthArray($auth);
}elseif(!$allowUnprotected){
$http->allowUnprotected=false;
$socks->on('connection',function(ConnectionInterface$conn)use($socks){
$remote=parse_url($conn->getRemoteAddress(),PHP_URL_HOST);
if($remote===null||ConnectorFactory::isIpLocal(trim($remote,'[]'))){
$socks->unsetAuth();
}else{
$socks->setAuth(function(){
return false;
});
}
});
}
return$socket;
}
}
namespace LeProxy\LeProxy;
class Logger
{
function logConnection($source,$destination,$remote){
$destination=$this->destination($destination);
if($remote!==null){
$remote=$this->destination($remote);
if($remote!==$destination){
$destination.=' ('.$remote.')';
}
}
$this->log($this->source($source).' connected to '.$destination);
}
function logFailConnection($source,$destination,$reason){
$this->log($this->source($source).' failed to connect to '.$this->destination($destination).' ('.$reason.')');
}
private function source($source){
$parts=parse_url($source);
if(isset($parts['scheme'],$parts['host'])){
$source=$parts['scheme'].'://';
if(isset($parts['user'])){
$source.=$parts['user'].'@';
}
$source.=$parts['host'];
}else{
$source='???';
}
return$source;
}
private function destination($destination){
$parts=parse_url((strpos($destination,'://')===false?'tcp://':'').$destination);
if($parts&&isset($parts['host'],$parts['port'])){
$destination=$parts['host'].':'.$parts['port'];
}
return$destination;
}
private function log($message){
$time=explode(' ',microtime(false));
echo date('Y-m-d H:i:s.',$time[1]).sprintf('%03d ',$time[0]*1000).$message.PHP_EOL;
}
}
namespace LeProxy\LeProxy;
use React\Socket\ConnectorInterface;
use React\Socket\ConnectionInterface;
class LoggingConnector implements ConnectorInterface
{
private$connector;
private$logger;
function __construct(ConnectorInterface$connector,Logger$logger){
$this->connector=$connector;
$this->logger=$logger;
}
function connect($uri){
$parts=parse_url(((strpos($uri,'://')===false)?'tcp://':'').$uri);
$args=array();
if(isset($parts['query'])){
parse_str($parts['query'],$args);
}
$source=isset($args['source'])?$args['source']:null;
return$this->connector->connect($uri)->then(function(ConnectionInterface$connection)use($source,$uri){
$this->logger->logConnection($source,$uri,$connection->getRemoteAddress());
return$connection;
},function(\Exception$e)use($source,$uri){
$this->logger->logFailConnection($source,$uri,$e->getMessage());
throw$e;
}
);
}
}
namespace React\Socket;
use Evenement\EventEmitterInterface;
interface ServerInterface extends EventEmitterInterface
{
function getAddress();
function pause();
function resume();
function close();
}
namespace LeProxy\LeProxy;
use Evenement\EventEmitter;
use React\Socket\ServerInterface;
class NullServer extends EventEmitter implements ServerInterface
{
function getAddress(){
return;
}
function pause(){
}
function resume(){
}
function close(){
}
}
namespace LeProxy\LeProxy;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;
use Exception;
class ProtocolDetector
{
var$http;
var$socks;
private$server;
function __construct(ServerInterface$server){
$this->server=$server;
$this->server->on('connection',array($this,'handleConnection'));
$this->http=new NullServer;
$this->socks=new NullServer;
$http=$this->http;
$this->server->on('error',function(Exception$e)use($http){
$http->emit('error',array($e));
});
}
function handleConnection(ConnectionInterface$connection){
$that=$this;
$connection->once('data',function($chunk)use($connection,$that){
if(isset($chunk[0])&&($chunk[0]==="\x05"||$chunk[0]==="\x04")){
$that->socks->emit('connection',array($connection));
}else{
$that->http->emit('connection',array($connection));
}
$connection->emit('data',array($chunk));
});
}
}
namespace LeProxy\LeProxy;
use React\Socket\ConnectorInterface;
use React\Promise\Timer\TimeoutException;
class SocksErrorConnector implements ConnectorInterface
{
private$connector;
function __construct(ConnectorInterface$connector){
$this->connector=$connector;
}
function connect($uri){
return$this->connector->connect($uri)->then(null,function(\Exception$e){
if($e->getCode()===ConnectorFactory::CODE_BLOCKED){
throw new\RuntimeException($e->getMessage().' (EACCES)',defined('SOCKET_ACCESS')?SOCKET_EACCES:13);
}
if($e instanceof TimeoutException||$e->getPrevious()===null){
throw$e;
}
throw new\RuntimeException($e->getMessage());
});
}
}
namespace LeProxy\LeProxy;
use React\Socket\ConnectorInterface;
class SourceConnector implements ConnectorInterface
{
private$connector;
private$source;
function __construct(ConnectorInterface$connector,$source){
$this->connector=$connector;
$this->source=$source;
}
function connect($uri){
$uri.=(strpos($uri,'?')===false?'?':'&').'source='.rawurlencode($this->source);
return$this->connector->connect($uri);
}
}
namespace Psr\Http\Message;
interface MessageInterface
{
function getProtocolVersion();
function withProtocolVersion($version);
function getHeaders();
function hasHeader($name);
function getHeader($name);
function getHeaderLine($name);
function withHeader($name,$value);
function withAddedHeader($name,$value);
function withoutHeader($name);
function getBody();
function withBody(StreamInterface$body);
}
namespace Psr\Http\Message;
interface RequestInterface extends MessageInterface
{
function getRequestTarget();
function withRequestTarget($requestTarget);
function getMethod();
function withMethod($method);
function getUri();
function withUri(UriInterface$uri,$preserveHost=false);
}
namespace Psr\Http\Message;
interface ResponseInterface extends MessageInterface
{
function getStatusCode();
function withStatus($code,$reasonPhrase='');
function getReasonPhrase();
}
namespace Psr\Http\Message;
interface ServerRequestInterface extends RequestInterface
{
function getServerParams();
function getCookieParams();
function withCookieParams(array$cookies);
function getQueryParams();
function withQueryParams(array$query);
function getUploadedFiles();
function withUploadedFiles(array$uploadedFiles);
function getParsedBody();
function withParsedBody($data);
function getAttributes();
function getAttribute($name,$default=null);
function withAttribute($name,$value);
function withoutAttribute($name);
}
namespace Psr\Http\Message;
interface StreamInterface
{
function __toString();
function close();
function detach();
function getSize();
function tell();
function eof();
function isSeekable();
function seek($offset,$whence=SEEK_SET);
function rewind();
function isWritable();
function write($string);
function isReadable();
function read($length);
function getContents();
function getMetadata($key=null);
}
namespace Psr\Http\Message;
interface UploadedFileInterface
{
function getStream();
function moveTo($targetPath);
function getSize();
function getError();
function getClientFilename();
function getClientMediaType();
}
namespace Psr\Http\Message;
interface UriInterface
{
function getScheme();
function getAuthority();
function getUserInfo();
function getHost();
function getPort();
function getPath();
function getQuery();
function getFragment();
function withScheme($scheme);
function withUserInfo($user,$password=null);
function withHost($host);
function withPort($port);
function withPath($path);
function withQuery($query);
function withFragment($fragment);
function __toString();
}
namespace React\Cache;
use React\Promise\PromiseInterface;
interface CacheInterface
{
function get($key,$default=null);
function set($key,$value,$ttl=null);
function delete($key);
}
namespace React\Cache;
use React\Promise;
class ArrayCache implements CacheInterface
{
private$limit;
private$data=array();
private$expires=array();
function __construct($limit=null){
$this->limit=$limit;
}
function get($key,$default=null){
if(isset($this->expires[$key])&&$this->expires[$key]<microtime(true)){
unset($this->data[$key],$this->expires[$key]);
}
if(!key_exists($key,$this->data)){
return Promise\resolve($default);
}
$value=$this->data[$key];
unset($this->data[$key]);
$this->data[$key]=$value;
return Promise\resolve($value);
}
function set($key,$value,$ttl=null){
unset($this->data[$key]);
$this->data[$key]=$value;
unset($this->expires[$key]);
if($ttl!==null){
$this->expires[$key]=microtime(true)+$ttl;
asort($this->expires);
}
if($this->limit!==null&&count($this->data)>$this->limit){
reset($this->expires);
$key=key($this->expires);
if($key===null||$this->expires[$key]>microtime(true)){
reset($this->data);
$key=key($this->data);
}
unset($this->data[$key],$this->expires[$key]);
}
return Promise\resolve(true);
}
function delete($key){
unset($this->data[$key],$this->expires[$key]);
return Promise\resolve(true);
}
}
namespace React\Dns;
class BadServerException extends\Exception
{
}
namespace React\Dns\Config;
use RuntimeException;
class Config
{
static function loadSystemConfigBlocking(){
if(DIRECTORY_SEPARATOR==='\\'){
return self::loadWmicBlocking();
}
try{
return self::loadResolvConfBlocking();
}catch(RuntimeException$ignored){
return new self;
}
}
static function loadResolvConfBlocking($path=null){
if($path===null){
$path='/etc/resolv.conf';
}
$contents=@file_get_contents($path);
if($contents===false){
throw new RuntimeException('Unable to load resolv.conf file "'.$path.'"');
}
preg_match_all('/^nameserver\s+(\S+)\s*$/m',$contents,$matches);
$config=new self;
$config->nameservers=$matches[1];
return$config;
}
static function loadWmicBlocking($command=null){
$contents=shell_exec($command===null?'wmic NICCONFIG get "DNSServerSearchOrder" /format:CSV':$command);
preg_match_all('/(?<=[{;,"])([\da-f.:]{4,})(?=[};,"])/i',$contents,$matches);
$config=new self;
$config->nameservers=$matches[1];
return$config;
}
var$nameservers=array();
}
namespace React\Dns\Config;
use React\EventLoop\LoopInterface;
use React\Promise;
use React\Promise\Deferred;
use React\Stream\ReadableResourceStream;
use React\Stream\Stream;
class FilesystemFactory
{
private$loop;
function __construct(LoopInterface$loop){
$this->loop=$loop;
}
function create($filename){
return$this
->loadEtcResolvConf($filename)->then(array($this,'parseEtcResolvConf'));
}
function parseEtcResolvConf($contents){
return Promise\resolve(Config::loadResolvConfBlocking('data://text/plain;base64,'.base64_encode($contents)));
}
function loadEtcResolvConf($filename){
if(!file_exists($filename)){
return Promise\reject(new\InvalidArgumentException("The filename for /etc/resolv.conf given does not exist: $filename"));
}
try{
$deferred=new Deferred;
$fd=fopen($filename,'r');
stream_set_blocking($fd,0);
$contents='';
$stream=class_exists('React\Stream\ReadableResourceStream')?new ReadableResourceStream($fd,$this->loop):new Stream($fd,$this->loop);
$stream->on('data',function($data)use(&$contents){
$contents.=$data;
});
$stream->on('end',function()use(&$contents,$deferred){
$deferred->resolve($contents);
});
$stream->on('error',function($error)use($deferred){
$deferred->reject($error);
});
return$deferred->promise();
}catch(\Exception$e){
return Promise\reject($e);
}
}
}
namespace React\Dns\Config;
use RuntimeException;
class HostsFile
{
static function getDefaultPath(){
if(DIRECTORY_SEPARATOR!=='\\'){
return'/etc/hosts';
}
$path='%SystemRoot%\\system32\drivers\etc\hosts';
$base=getenv('SystemRoot');
if($base===false){
$base='C:\\Windows';
}
return str_replace('%SystemRoot%',$base,$path);
}
static function loadFromPathBlocking($path=null){
if($path===null){
$path=self::getDefaultPath();
}
$contents=@file_get_contents($path);
if($contents===false){
throw new RuntimeException('Unable to load hosts file "'.$path.'"');
}
return new self($contents);
}
function __construct($contents){
$contents=preg_replace('/[ \t]*#.*/','',strtolower($contents));
$this->contents=$contents;
}
function getIpsForHost($name){
$name=strtolower($name);
$ips=array();
foreach(preg_split('/\r?\n/',$this->contents)as$line){
$parts=preg_split('/\s+/',$line);
$ip=array_shift($parts);
if($parts&&array_search($name,$parts)!==false){
if(strpos($ip,':')!==false&&($pos=strpos($ip,'%'))!==false){
$ip=substr($ip,0,$pos);
}
if(@inet_pton($ip)!==false){
$ips[]=$ip;
}
}
}
return$ips;
}
function getHostsForIp($ip){
$ip=@inet_pton($ip);
if($ip===false){
return array();
}
$names=array();
foreach(preg_split('/\r?\n/',$this->contents)as$line){
$parts=preg_split('/\s+/',$line,null,PREG_SPLIT_NO_EMPTY);
$addr=array_shift($parts);
if(strpos($addr,':')!==false&&($pos=strpos($addr,'%'))!==false){
$addr=substr($addr,0,$pos);
}
if(@inet_pton($addr)===$ip){
foreach($parts as$part){
$names[]=$part;
}
}
}
return$names;
}
}
namespace React\Dns\Model;
class HeaderBag
{
var$attributes=array('qdCount'=>0,'anCount'=>0,'nsCount'=>0,'arCount'=>0,'qr'=>0,'opcode'=>Message::OPCODE_QUERY,'aa'=>0,'tc'=>0,'rd'=>0,'ra'=>0,'z'=>0,'rcode'=>Message::RCODE_OK,);
var$data='';
function get($name){
return isset($this->attributes[$name])?$this->attributes[$name]:null;
}
function set($name,$value){
$this->attributes[$name]=$value;
}
function isQuery(){
return 0===$this->attributes['qr'];
}
function isResponse(){
return 1===$this->attributes['qr'];
}
function isTruncated(){
return 1===$this->attributes['tc'];
}
function populateCounts(Message$message){
$this->attributes['qdCount']=count($message->questions);
$this->attributes['anCount']=count($message->answers);
$this->attributes['nsCount']=count($message->authority);
$this->attributes['arCount']=count($message->additional);
}
}
namespace React\Dns\Model;
use React\Dns\Query\Query;
class Message
{
const TYPE_A=1;
const TYPE_NS=2;
const TYPE_CNAME=5;
const TYPE_SOA=6;
const TYPE_PTR=12;
const TYPE_MX=15;
const TYPE_TXT=16;
const TYPE_AAAA=28;
const TYPE_SRV=33;
const TYPE_ANY=255;
const CLASS_IN=1;
const OPCODE_QUERY=0;
const OPCODE_IQUERY=1;const OPCODE_STATUS=2;
const RCODE_OK=0;
const RCODE_FORMAT_ERROR=1;
const RCODE_SERVER_FAILURE=2;
const RCODE_NAME_ERROR=3;
const RCODE_NOT_IMPLEMENTED=4;
const RCODE_REFUSED=5;
static function createRequestForQuery(Query$query){
$request=new Message;
$request->header->set('id',self::generateId());
$request->header->set('rd',1);
$request->questions[]=(array)$query;
$request->prepare();
return$request;
}
static function createResponseWithAnswersForQuery(Query$query,array$answers){
$response=new Message;
$response->header->set('id',self::generateId());
$response->header->set('qr',1);
$response->header->set('opcode',Message::OPCODE_QUERY);
$response->header->set('rd',1);
$response->header->set('rcode',Message::RCODE_OK);
$response->questions[]=(array)$query;
foreach($answers as$record){
$response->answers[]=$record;
}
$response->prepare();
return$response;
}
private static function generateId(){
if(function_exists('random_int')){
return random_int(0,65535);
}
return mt_rand(0,65535);
}
var$header;
var$questions=array();
var$answers=array();
var$authority=array();
var$additional=array();
var$data='';
var$consumed=0;
function __construct(){
$this->header=new HeaderBag;
}
function getId(){
return$this->header->get('id');
}
function getResponseCode(){
return$this->header->get('rcode');
}
function prepare(){
$this->header->populateCounts($this);
}
}
namespace React\Dns\Model;
class Record
{
var$name;
var$type;
var$class;
var$ttl;
var$data;
function __construct($name,$type,$class,$ttl=0,$data=null){
$this->name=$name;
$this->type=$type;
$this->class=$class;
$this->ttl=$ttl;
$this->data=$data;
}
}
namespace React\Dns\Protocol;
use React\Dns\Model\Message;
use React\Dns\Model\HeaderBag;
class BinaryDumper
{
function toBinary(Message$message){
$data='';
$data.=$this->headerToBinary($message->header);
$data.=$this->questionToBinary($message->questions);
return$data;
}
private function headerToBinary(HeaderBag$header){
$data='';
$data.=pack('n',$header->get('id'));
$flags=0;
$flags=($flags<<1)|$header->get('qr');
$flags=($flags<<4)|$header->get('opcode');
$flags=($flags<<1)|$header->get('aa');
$flags=($flags<<1)|$header->get('tc');
$flags=($flags<<1)|$header->get('rd');
$flags=($flags<<1)|$header->get('ra');
$flags=($flags<<3)|$header->get('z');
$flags=($flags<<4)|$header->get('rcode');
$data.=pack('n',$flags);
$data.=pack('n',$header->get('qdCount'));
$data.=pack('n',$header->get('anCount'));
$data.=pack('n',$header->get('nsCount'));
$data.=pack('n',$header->get('arCount'));
return$data;
}
private function questionToBinary(array$questions){
$data='';
foreach($questions as$question){
$labels=explode('.',$question['name']);
foreach($labels as$label){
$data.=chr(strlen($label)).$label;
}
$data.="\x00";
$data.=pack('n*',$question['type'],$question['class']);
}
return$data;
}
}
namespace React\Dns\Protocol;
use React\Dns\Model\Message;
use React\Dns\Model\Record;
use InvalidArgumentException;
class Parser
{
function parseMessage($data){
$message=new Message;
if($this->parse($data,$message)!==$message){
throw new InvalidArgumentException('Unable to parse binary message');
}
return$message;
}
function parseChunk($data,Message$message){
return$this->parse($data,$message);
}
private function parse($data,Message$message){
$message->data.=$data;
if(!$message->header->get('id')){
if(!$this->parseHeader($message)){
return;
}
}
if($message->header->get('qdCount')!=count($message->questions)){
if(!$this->parseQuestion($message)){
return;
}
}
if($message->header->get('anCount')!=count($message->answers)){
if(!$this->parseAnswer($message)){
return;
}
}
return$message;
}
function parseHeader(Message$message){
if(strlen($message->data)<12){
return;
}
$header=substr($message->data,0,12);
$message->consumed+=12;
list($id,$fields,$qdCount,$anCount,$nsCount,$arCount)=array_values(unpack('n*',$header));
$rcode=$fields&bindec('1111');
$z=($fields>>4)&bindec('111');
$ra=($fields>>7)&1;
$rd=($fields>>8)&1;
$tc=($fields>>9)&1;
$aa=($fields>>10)&1;
$opcode=($fields>>11)&bindec('1111');
$qr=($fields>>15)&1;
$vars=compact('id','qdCount','anCount','nsCount','arCount','qr','opcode','aa','tc','rd','ra','z','rcode');
foreach($vars as$name=>$value){
$message->header->set($name,$value);
}
return$message;
}
function parseQuestion(Message$message){
if(strlen($message->data)<2){
return;
}
$consumed=$message->consumed;
list($labels,$consumed)=$this->readLabels($message->data,$consumed);
if(null===$labels){
return;
}
if(strlen($message->data)-$consumed<4){
return;
}
list($type,$class)=array_values(unpack('n*',substr($message->data,$consumed,4)));
$consumed+=4;
$message->consumed=$consumed;
$message->questions[]=array('name'=>join('.',$labels),'type'=>$type,'class'=>$class,);
if($message->header->get('qdCount')!=count($message->questions)){
return$this->parseQuestion($message);
}
return$message;
}
function parseAnswer(Message$message){
if(strlen($message->data)<2){
return;
}
$consumed=$message->consumed;
list($labels,$consumed)=$this->readLabels($message->data,$consumed);
if(null===$labels){
return;
}
if(strlen($message->data)-$consumed<10){
return;
}
list($type,$class)=array_values(unpack('n*',substr($message->data,$consumed,4)));
$consumed+=4;
list($ttl)=array_values(unpack('N',substr($message->data,$consumed,4)));
$consumed+=4;
list($rdLength)=array_values(unpack('n',substr($message->data,$consumed,2)));
$consumed+=2;
$rdata=null;
if(Message::TYPE_A===$type||Message::TYPE_AAAA===$type){
$ip=substr($message->data,$consumed,$rdLength);
$consumed+=$rdLength;
$rdata=inet_ntop($ip);
}elseif(Message::TYPE_CNAME===$type||Message::TYPE_PTR===$type||Message::TYPE_NS===$type){
list($bodyLabels,$consumed)=$this->readLabels($message->data,$consumed);
$rdata=join('.',$bodyLabels);
}elseif(Message::TYPE_TXT===$type){
$rdata=array();
$remaining=$rdLength;
while($remaining){
$len=ord($message->data[$consumed]);
$rdata[]=substr($message->data,$consumed+1,$len);
$consumed+=$len+1;
$remaining-=$len+1;
}
}elseif(Message::TYPE_MX===$type){
list($priority)=array_values(unpack('n',substr($message->data,$consumed,2)));
list($bodyLabels,$consumed)=$this->readLabels($message->data,$consumed+2);
$rdata=array('priority'=>$priority,'target'=>join('.',$bodyLabels));
}elseif(Message::TYPE_SRV===$type){
list($priority,$weight,$port)=array_values(unpack('n*',substr($message->data,$consumed,6)));
list($bodyLabels,$consumed)=$this->readLabels($message->data,$consumed+6);
$rdata=array('priority'=>$priority,'weight'=>$weight,'port'=>$port,'target'=>join('.',$bodyLabels));
}elseif(Message::TYPE_SOA===$type){
list($primaryLabels,$consumed)=$this->readLabels($message->data,$consumed);
list($mailLabels,$consumed)=$this->readLabels($message->data,$consumed);
list($serial,$refresh,$retry,$expire,$minimum)=array_values(unpack('N*',substr($message->data,$consumed,20)));
$consumed+=20;
$rdata=array('mname'=>join('.',$primaryLabels),'rname'=>join('.',$mailLabels),'serial'=>$serial,'refresh'=>$refresh,'retry'=>$retry,'expire'=>$expire,'minimum'=>$minimum
);
}else{
$rdata=substr($message->data,$consumed,$rdLength);
$consumed+=$rdLength;
}
$message->consumed=$consumed;
$name=join('.',$labels);
$ttl=$this->signedLongToUnsignedLong($ttl);
$record=new Record($name,$type,$class,$ttl,$rdata);
$message->answers[]=$record;
if($message->header->get('anCount')!=count($message->answers)){
return$this->parseAnswer($message);
}
return$message;
}
private function readLabels($data,$consumed){
$labels=array();
while(true){
if($this->isEndOfLabels($data,$consumed)){
$consumed+=1;
break;
}
if($this->isCompressedLabel($data,$consumed)){
list($newLabels,$consumed)=$this->getCompressedLabel($data,$consumed);
$labels=array_merge($labels,$newLabels);
break;
}
$length=ord(substr($data,$consumed,1));
$consumed+=1;
if(strlen($data)-$consumed<$length){
return array(null,null);
}
$labels[]=substr($data,$consumed,$length);
$consumed+=$length;
}
return array($labels,$consumed);
}
function isEndOfLabels($data,$consumed){
$length=ord(substr($data,$consumed,1));
return 0===$length;
}
function getCompressedLabel($data,$consumed){
list($nameOffset,$consumed)=$this->getCompressedLabelOffset($data,$consumed);
list($labels)=$this->readLabels($data,$nameOffset);
return array($labels,$consumed);
}
function isCompressedLabel($data,$consumed){
$mask=49152;list($peek)=array_values(unpack('n',substr($data,$consumed,2)));
return(bool)($peek&$mask);
}
function getCompressedLabelOffset($data,$consumed){
$mask=16383;list($peek)=array_values(unpack('n',substr($data,$consumed,2)));
return array($peek&$mask,$consumed+2);
}
function signedLongToUnsignedLong($i){
return$i&2147483648?$i-4294967295:$i;
}
}
namespace React\Dns\Query;
interface ExecutorInterface
{
function query($nameserver,Query$query);
}
namespace React\Dns\Query;
use React\Dns\Model\Message;
class CachedExecutor implements ExecutorInterface
{
private$executor;
private$cache;
function __construct(ExecutorInterface$executor,RecordCache$cache){
$this->executor=$executor;
$this->cache=$cache;
}
function query($nameserver,Query$query){
$executor=$this->executor;
$cache=$this->cache;
return$this->cache
->lookup($query)->then(function($cachedRecords)use($query){
return Message::createResponseWithAnswersForQuery($query,$cachedRecords);
},function()use($executor,$cache,$nameserver,$query){
return$executor
->query($nameserver,$query)->then(function($response)use($cache,$query){
$cache->storeResponseMessage($query->currentTime,$response);
return$response;
});
}
);
}
function buildResponse(Query$query,array$cachedRecords){
return Message::createResponseWithAnswersForQuery($query,$cachedRecords);
}
protected function generateId(){
return mt_rand(0,65535);
}
}
namespace React\Dns\Query;
class CancellationException extends\RuntimeException
{
}
namespace React\Dns\Query;
use React\Dns\Model\Message;
use React\Dns\Protocol\Parser;
use React\Dns\Protocol\BinaryDumper;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise;
use React\Stream\DuplexResourceStream;
use React\Stream\Stream;
class Executor implements ExecutorInterface
{
private$loop;
private$parser;
private$dumper;
private$timeout;
function __construct(LoopInterface$loop,Parser$parser,BinaryDumper$dumper,$timeout=5){
$this->loop=$loop;
$this->parser=$parser;
$this->dumper=$dumper;
$this->timeout=$timeout;
}
function query($nameserver,Query$query){
$request=Message::createRequestForQuery($query);
$queryData=$this->dumper->toBinary($request);
$transport=strlen($queryData)>512?'tcp':'udp';
return$this->doQuery($nameserver,$transport,$queryData,$query->name);
}
function prepareRequest(Query$query){
return Message::createRequestForQuery($query);
}
function doQuery($nameserver,$transport,$queryData,$name){
if($transport!=='udp'){
return Promise\reject(new\RuntimeException('DNS query for '.$name.' failed: Requested transport "'.$transport.'" not available, only UDP is supported in this version'));
}
$that=$this;
$parser=$this->parser;
$loop=$this->loop;
try{
$conn=$this->createConnection($nameserver,$transport);
}catch(\Exception$e){
return Promise\reject(new\RuntimeException('DNS query for '.$name.' failed: '.$e->getMessage(),0,$e));
}
$deferred=new Deferred(function($resolve,$reject)use(&$timer,$loop,&$conn,$name){
$reject(new CancellationException(sprintf('DNS query for %s has been cancelled',$name)));
if($timer!==null){
$loop->cancelTimer($timer);
}
$conn->close();
});
$timer=null;
if($this->timeout!==null){
$timer=$this->loop->addTimer($this->timeout,function()use(&$conn,$name,$deferred){
$conn->close();
$deferred->reject(new TimeoutException(sprintf("DNS query for %s timed out",$name)));
});
}
$conn->on('data',function($data)use($conn,$parser,$deferred,$timer,$loop,$name){
$conn->end();
if($timer!==null){
$loop->cancelTimer($timer);
}
try{
$response=$parser->parseMessage($data);
}catch(\Exception$e){
$deferred->reject($e);
return;
}
if($response->header->isTruncated()){
$deferred->reject(new\RuntimeException('DNS query for '.$name.' failed: The server returned a truncated result for a UDP query, but retrying via TCP is currently not supported'));
return;
}
$deferred->resolve($response);
});
$conn->write($queryData);
return$deferred->promise();
}
protected function generateId(){
return mt_rand(0,65535);
}
protected function createConnection($nameserver,$transport){
$fd=@stream_socket_client("$transport://$nameserver",$errno,$errstr,0,STREAM_CLIENT_CONNECT|STREAM_CLIENT_ASYNC_CONNECT);
if($fd===false){
throw new\RuntimeException('Unable to connect to DNS server: '.$errstr,$errno);
}
if(!class_exists('React\Stream\Stream')){
$conn=new DuplexResourceStream($fd,$this->loop,-1);
}else{
$conn=new Stream($fd,$this->loop);
$conn->bufferSize=null;
}
return$conn;
}
}
namespace React\Dns\Query;
use React\Dns\Config\HostsFile;
use React\Dns\Model\Message;
use React\Dns\Model\Record;
use React\Promise;
class HostsFileExecutor implements ExecutorInterface
{
private$hosts;
private$fallback;
function __construct(HostsFile$hosts,ExecutorInterface$fallback){
$this->hosts=$hosts;
$this->fallback=$fallback;
}
function query($nameserver,Query$query){
if($query->class===Message::CLASS_IN&&($query->type===Message::TYPE_A||$query->type===Message::TYPE_AAAA)){
$records=array();
$expectsColon=$query->type===Message::TYPE_AAAA;
foreach($this->hosts->getIpsForHost($query->name)as$ip){
if((strpos($ip,':')!==false)===$expectsColon){
$records[]=new Record($query->name,$query->type,$query->class,0,$ip);
}
}
if($records){
return Promise\resolve(Message::createResponseWithAnswersForQuery($query,$records));
}
}elseif($query->class===Message::CLASS_IN&&$query->type===Message::TYPE_PTR){
$ip=$this->getIpFromHost($query->name);
if($ip!==null){
$records=array();
foreach($this->hosts->getHostsForIp($ip)as$host){
$records[]=new Record($query->name,$query->type,$query->class,0,$host);
}
if($records){
return Promise\resolve(Message::createResponseWithAnswersForQuery($query,$records));
}
}
}
return$this->fallback->query($nameserver,$query);
}
private function getIpFromHost($host){
if(substr($host,-13)==='.in-addr.arpa'){
$ip=@inet_pton(substr($host,0,-13));
if($ip===false||isset($ip[4])){
return;
}
return inet_ntop(strrev($ip));
}elseif(substr($host,-9)==='.ip6.arpa'){
$ip=@inet_ntop(pack('H*',strrev(str_replace('.','',substr($host,0,-9)))));
if($ip===false){
return;
}
return$ip;
}else{
return;
}
}
}
namespace React\Dns\Query;
class Query
{
var$name;
var$type;
var$class;
var$currentTime;
function __construct($name,$type,$class,$currentTime=null){
if($currentTime===null){
$currentTime=time();
}
$this->name=$name;
$this->type=$type;
$this->class=$class;
$this->currentTime=$currentTime;
}
}
namespace React\Dns\Query;
use React\Dns\Model\Message;
use React\Dns\Model\Record;
class RecordBag
{
private$records=array();
function set($currentTime,Record$record){
$this->records[$record->data]=array($currentTime+$record->ttl,$record);
}
function all(){
return array_values(array_map(function($value){
list($expiresAt,$record)=$value;
return$record;
},$this->records
));
}
}
namespace React\Dns\Query;
use React\Cache\CacheInterface;
use React\Dns\Model\Message;
use React\Dns\Model\Record;
use React\Promise;
use React\Promise\PromiseInterface;
class RecordCache
{
private$cache;
private$expiredAt;
function __construct(CacheInterface$cache){
$this->cache=$cache;
}
function lookup(Query$query){
$id=$this->serializeQueryToIdentity($query);
$expiredAt=$this->expiredAt;
return$this->cache
->get($id)->then(function($value)use($query,$expiredAt){
if($value===null){
return Promise\reject();
}
$recordBag=unserialize($value);
if(null!==$expiredAt&&$expiredAt<=$query->currentTime){
return Promise\reject();
}
return$recordBag->all();
});
}
function storeResponseMessage($currentTime,Message$message){
foreach($message->answers as$record){
$this->storeRecord($currentTime,$record);
}
}
function storeRecord($currentTime,Record$record){
$id=$this->serializeRecordToIdentity($record);
$cache=$this->cache;
$this->cache
->get($id)->then(function($value){
if($value===null){
return new RecordBag;
}
return unserialize($value);
},function($e){
return new RecordBag;
}
)->then(function(RecordBag$recordBag)use($id,$currentTime,$record,$cache){
$recordBag->set($currentTime,$record);
$cache->set($id,serialize($recordBag));
});
}
function expire($currentTime){
$this->expiredAt=$currentTime;
}
function serializeQueryToIdentity(Query$query){
return sprintf('%s:%s:%s',$query->name,$query->type,$query->class);
}
function serializeRecordToIdentity(Record$record){
return sprintf('%s:%s:%s',$record->name,$record->type,$record->class);
}
}
namespace React\Dns\Query;
use React\Promise\Deferred;
class RetryExecutor implements ExecutorInterface
{
private$executor;
private$retries;
function __construct(ExecutorInterface$executor,$retries=2){
$this->executor=$executor;
$this->retries=$retries;
}
function query($nameserver,Query$query){
return$this->tryQuery($nameserver,$query,$this->retries);
}
function tryQuery($nameserver,Query$query,$retries){
$that=$this;
$errorback=function($error)use($nameserver,$query,$retries,$that){
if(!$error instanceof TimeoutException){
throw$error;
}
if(0>=$retries){
throw new\RuntimeException(sprintf("DNS query for %s failed: too many retries",$query->name),0,$error
);
}
return$that->tryQuery($nameserver,$query,$retries-1);
};
return$this->executor
->query($nameserver,$query)->then(null,$errorback);
}
}
namespace React\Dns\Query;
class TimeoutException extends\Exception
{
}
namespace React\Dns\Query;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\CancellablePromiseInterface;
use React\Promise\Timer;
class TimeoutExecutor implements ExecutorInterface
{
private$executor;
private$loop;
private$timeout;
function __construct(ExecutorInterface$executor,$timeout,LoopInterface$loop){
$this->executor=$executor;
$this->loop=$loop;
$this->timeout=$timeout;
}
function query($nameserver,Query$query){
return Timer\timeout($this->executor->query($nameserver,$query),$this->timeout,$this->loop)->then(null,function($e)use($query){
if($e instanceof Timer\TimeoutException){
$e=new TimeoutException(sprintf("DNS query for %s timed out",$query->name),0,$e);
}
throw$e;
});
}
}
namespace React\Dns\Query;
use React\Dns\Model\Message;
use React\Dns\Protocol\BinaryDumper;
use React\Dns\Protocol\Parser;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
class UdpTransportExecutor implements ExecutorInterface
{
private$loop;
private$parser;
private$dumper;
function __construct(LoopInterface$loop,Parser$parser=null,BinaryDumper$dumper=null){
if($parser===null){
$parser=new Parser;
}
if($dumper===null){
$dumper=new BinaryDumper;
}
$this->loop=$loop;
$this->parser=$parser;
$this->dumper=$dumper;
}
function query($nameserver,Query$query){
$request=Message::createRequestForQuery($query);
$queryData=$this->dumper->toBinary($request);
if(isset($queryData[512])){
return\React\Promise\reject(new\RuntimeException('DNS query for '.$query->name.' failed: Query too large for UDP transport'));
}
$socket=@\stream_socket_client("udp://$nameserver",$errno,$errstr,0);
if($socket===false){
return\React\Promise\reject(new\RuntimeException('DNS query for '.$query->name.' failed: Unable to connect to DNS server ('.$errstr.')',$errno
));
}
\stream_set_blocking($socket,false);
\fputs($socket,$queryData);
$loop=$this->loop;
$deferred=new Deferred(function()use($loop,$socket,$query){
$loop->removeReadStream($socket);
\fclose($socket);
throw new CancellationException('DNS query for '.$query->name.' has been cancelled');
});
$parser=$this->parser;
$loop->addReadStream($socket,function($socket)use($loop,$deferred,$query,$parser,$request){
$data=@\fread($socket,512);
try{
$response=$parser->parseMessage($data);
}catch(\Exception$e){
return;
}
if($response->getId()!==$request->getId()){
return;
}
$loop->removeReadStream($socket);
\fclose($socket);
if($response->header->isTruncated()){
$deferred->reject(new\RuntimeException('DNS query for '.$query->name.' failed: The server returned a truncated result for a UDP query, but retrying via TCP is currently not supported'));
return;
}
$deferred->resolve($response);
});
return$deferred->promise();
}
}
namespace React\Dns;
class RecordNotFoundException extends\Exception
{
}
namespace React\Dns\Resolver;
use React\Cache\ArrayCache;
use React\Cache\CacheInterface;
use React\Dns\Config\HostsFile;
use React\Dns\Query\CachedExecutor;
use React\Dns\Query\ExecutorInterface;
use React\Dns\Query\HostsFileExecutor;
use React\Dns\Query\RecordCache;
use React\Dns\Query\RetryExecutor;
use React\Dns\Query\TimeoutExecutor;
use React\Dns\Query\UdpTransportExecutor;
use React\EventLoop\LoopInterface;
class Factory
{
function create($nameserver,LoopInterface$loop){
$nameserver=$this->addPortToServerIfMissing($nameserver);
$executor=$this->decorateHostsFileExecutor($this->createRetryExecutor($loop));
return new Resolver($nameserver,$executor);
}
function createCached($nameserver,LoopInterface$loop,CacheInterface$cache=null){
if(!($cache instanceof CacheInterface)){
$cache=new ArrayCache;
}
$nameserver=$this->addPortToServerIfMissing($nameserver);
$executor=$this->decorateHostsFileExecutor($this->createCachedExecutor($loop,$cache));
return new Resolver($nameserver,$executor);
}
private function decorateHostsFileExecutor(ExecutorInterface$executor){
try{
$executor=new HostsFileExecutor(HostsFile::loadFromPathBlocking(),$executor
);
}catch(\RuntimeException$e){
}
if(DIRECTORY_SEPARATOR==='\\'){
$executor=new HostsFileExecutor(new HostsFile("127.0.0.1 localhost\n::1 localhost"),$executor
);
}
return$executor;
}
protected function createExecutor(LoopInterface$loop){
return new TimeoutExecutor(new UdpTransportExecutor($loop),5.0,$loop
);
}
protected function createRetryExecutor(LoopInterface$loop){
return new RetryExecutor($this->createExecutor($loop));
}
protected function createCachedExecutor(LoopInterface$loop,CacheInterface$cache){
return new CachedExecutor($this->createRetryExecutor($loop),new RecordCache($cache));
}
protected function addPortToServerIfMissing($nameserver){
if(strpos($nameserver,'[')===false&&substr_count($nameserver,':')>=2){
$nameserver='['.$nameserver.']';
}
if(parse_url('dummy://'.$nameserver,PHP_URL_PORT)===null){
$nameserver.=':53';
}
return$nameserver;
}
}
namespace React\Dns\Resolver;
use React\Dns\Model\Message;
use React\Dns\Query\ExecutorInterface;
use React\Dns\Query\Query;
use React\Dns\RecordNotFoundException;
use React\Promise\PromiseInterface;
class Resolver
{
private$nameserver;
private$executor;
function __construct($nameserver,ExecutorInterface$executor){
$this->nameserver=$nameserver;
$this->executor=$executor;
}
function resolve($domain){
return$this->resolveAll($domain,Message::TYPE_A)->then(function(array$ips){
return$ips[array_rand($ips)];
});
}
function resolveAll($domain,$type){
$query=new Query($domain,$type,Message::CLASS_IN);
$that=$this;
return$this->executor->query($this->nameserver,$query
)->then(function(Message$response)use($query,$that){
return$that->extractValues($query,$response);
});
}
function extractAddress(Query$query,Message$response){
$addresses=$this->extractValues($query,$response);
return$addresses[array_rand($addresses)];
}
function extractValues(Query$query,Message$response){
$code=$response->getResponseCode();
if($code!==Message::RCODE_OK){
switch($code){
case Message::RCODE_FORMAT_ERROR:$message='Format Error';
break;
case Message::RCODE_SERVER_FAILURE:$message='Server Failure';
break;
case Message::RCODE_NAME_ERROR:$message='Non-Existent Domain / NXDOMAIN';
break;
case Message::RCODE_NOT_IMPLEMENTED:$message='Not Implemented';
break;
case Message::RCODE_REFUSED:$message='Refused';
break;
default:$message='Unknown error response code '.$code;
}
throw new RecordNotFoundException('DNS query for '.$query->name.' returned an error response ('.$message.')',$code
);
}
$answers=$response->answers;
$addresses=$this->valuesByNameAndType($answers,$query->name,$query->type);
if(0===count($addresses)){
throw new RecordNotFoundException('DNS query for '.$query->name.' did not return a valid answer (NOERROR / NODATA)');
}
return array_values($addresses);
}
function resolveAliases(array$answers,$name){
return$this->valuesByNameAndType($answers,$name,Message::TYPE_A);
}
private function valuesByNameAndType(array$answers,$name,$type){
$named=$this->filterByName($answers,$name);
$records=$this->filterByType($named,$type);
if($records){
return$this->mapRecordData($records);
}
$cnameRecords=$this->filterByType($named,Message::TYPE_CNAME);
if($cnameRecords){
$cnames=$this->mapRecordData($cnameRecords);
foreach($cnames as$cname){
$records=array_merge($records,$this->valuesByNameAndType($answers,$cname,$type));
}
}
return$records;
}
private function filterByName(array$answers,$name){
return$this->filterByField($answers,'name',$name);
}
private function filterByType(array$answers,$type){
return$this->filterByField($answers,'type',$type);
}
private function filterByField(array$answers,$field,$value){
$value=strtolower($value);
return array_filter($answers,function($answer)use($field,$value){
return$value===strtolower($answer->$field);
});
}
private function mapRecordData(array$records){
return array_map(function($record){
return$record->data;
},$records);
}
}
namespace React\EventLoop;
interface LoopInterface
{
function addReadStream($stream,$listener);
function addWriteStream($stream,$listener);
function removeReadStream($stream);
function removeWriteStream($stream);
function addTimer($interval,$callback);
function addPeriodicTimer($interval,$callback);
function cancelTimer(TimerInterface$timer);
function futureTick($listener);
function addSignal($signal,$listener);
function removeSignal($signal,$listener);
function run();
function stop();
}
namespace React\EventLoop;
use Ev;
use EvIo;
use EvLoop;
use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Timer\Timer;
use SplObjectStorage;
class ExtEvLoop implements LoopInterface
{
private$loop;
private$futureTickQueue;
private$timers;
private$readStreams=array();
private$writeStreams=array();
private$running;
private$signals;
private$signalEvents=array();
function __construct(){
$this->loop=new EvLoop;
$this->futureTickQueue=new FutureTickQueue;
$this->timers=new SplObjectStorage;
$this->signals=new SignalsHandler;
}
function addReadStream($stream,$listener){
$key=(int)$stream;
if(isset($this->readStreams[$key])){
return;
}
$callback=$this->getStreamListenerClosure($stream,$listener);
$event=$this->loop->io($stream,Ev::READ,$callback);
$this->readStreams[$key]=$event;
}
private function getStreamListenerClosure($stream,$listener){
return function()use($stream,$listener){
\call_user_func($listener,$stream);
};
}
function addWriteStream($stream,$listener){
$key=(int)$stream;
if(isset($this->writeStreams[$key])){
return;
}
$callback=$this->getStreamListenerClosure($stream,$listener);
$event=$this->loop->io($stream,Ev::WRITE,$callback);
$this->writeStreams[$key]=$event;
}
function removeReadStream($stream){
$key=(int)$stream;
if(!isset($this->readStreams[$key])){
return;
}
$this->readStreams[$key]->stop();
unset($this->readStreams[$key]);
}
function removeWriteStream($stream){
$key=(int)$stream;
if(!isset($this->writeStreams[$key])){
return;
}
$this->writeStreams[$key]->stop();
unset($this->writeStreams[$key]);
}
function addTimer($interval,$callback){
$timer=new Timer($interval,$callback,false);
$that=$this;
$timers=$this->timers;
$callback=function()use($timer,$timers,$that){
\call_user_func($timer->getCallback(),$timer);
if($timers->contains($timer)){
$that->cancelTimer($timer);
}
};
$event=$this->loop->timer($timer->getInterval(),0.0,$callback);
$this->timers->attach($timer,$event);
return$timer;
}
function addPeriodicTimer($interval,$callback){
$timer=new Timer($interval,$callback,true);
$callback=function()use($timer){
\call_user_func($timer->getCallback(),$timer);
};
$event=$this->loop->timer($interval,$interval,$callback);
$this->timers->attach($timer,$event);
return$timer;
}
function cancelTimer(TimerInterface$timer){
if(!isset($this->timers[$timer])){
return;
}
$event=$this->timers[$timer];
$event->stop();
$this->timers->detach($timer);
}
function futureTick($listener){
$this->futureTickQueue->add($listener);
}
function run(){
$this->running=true;
while($this->running){
$this->futureTickQueue->tick();
$hasPendingCallbacks=!$this->futureTickQueue->isEmpty();
$wasJustStopped=!$this->running;
$nothingLeftToDo=!$this->readStreams
&&!$this->writeStreams
&&!$this->timers->count()&&$this->signals->isEmpty();
$flags=Ev::RUN_ONCE;
if($wasJustStopped||$hasPendingCallbacks){
$flags|=Ev::RUN_NOWAIT;
}elseif($nothingLeftToDo){
break;
}
$this->loop->run($flags);
}
}
function stop(){
$this->running=false;
}
function __destruct(){
foreach($this->timers as$timer){
$this->cancelTimer($timer);
}
foreach($this->readStreams as$key=>$stream){
$this->removeReadStream($key);
}
foreach($this->writeStreams as$key=>$stream){
$this->removeWriteStream($key);
}
}
function addSignal($signal,$listener){
$this->signals->add($signal,$listener);
if(!isset($this->signalEvents[$signal])){
$this->signalEvents[$signal]=$this->loop->signal($signal,function()use($signal){
$this->signals->call($signal);
});
}
}
function removeSignal($signal,$listener){
$this->signals->remove($signal,$listener);
if(isset($this->signalEvents[$signal])){
$this->signalEvents[$signal]->stop();
unset($this->signalEvents[$signal]);
}
}
}
namespace React\EventLoop;
use BadMethodCallException;
use Event;
use EventBase;
use EventConfig as EventBaseConfig;
use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Timer\Timer;
use SplObjectStorage;
final class ExtEventLoop implements LoopInterface
{
private$eventBase;
private$futureTickQueue;
private$timerCallback;
private$timerEvents;
private$streamCallback;
private$readEvents=array();
private$writeEvents=array();
private$readListeners=array();
private$writeListeners=array();
private$readRefs=array();
private$writeRefs=array();
private$running;
private$signals;
private$signalEvents=array();
function __construct(){
if(!\class_exists('EventBase',false)){
throw new BadMethodCallException('Cannot create ExtEventLoop, ext-event extension missing');
}
$config=new EventBaseConfig;
$config->requireFeatures(EventBaseConfig::FEATURE_FDS);
$this->eventBase=new EventBase($config);
$this->futureTickQueue=new FutureTickQueue;
$this->timerEvents=new SplObjectStorage;
$this->signals=new SignalsHandler;
$this->createTimerCallback();
$this->createStreamCallback();
}
function addReadStream($stream,$listener){
$key=(int)$stream;
if(isset($this->readListeners[$key])){
return;
}
$event=new Event($this->eventBase,$stream,Event::PERSIST|Event::READ,$this->streamCallback);
$event->add();
$this->readEvents[$key]=$event;
$this->readListeners[$key]=$listener;
if(\PHP_VERSION_ID>=70000){
$this->readRefs[$key]=$stream;
}
}
function addWriteStream($stream,$listener){
$key=(int)$stream;
if(isset($this->writeListeners[$key])){
return;
}
$event=new Event($this->eventBase,$stream,Event::PERSIST|Event::WRITE,$this->streamCallback);
$event->add();
$this->writeEvents[$key]=$event;
$this->writeListeners[$key]=$listener;
if(\PHP_VERSION_ID>=70000){
$this->writeRefs[$key]=$stream;
}
}
function removeReadStream($stream){
$key=(int)$stream;
if(isset($this->readEvents[$key])){
$this->readEvents[$key]->free();
unset($this->readEvents[$key],$this->readListeners[$key],$this->readRefs[$key]);
}
}
function removeWriteStream($stream){
$key=(int)$stream;
if(isset($this->writeEvents[$key])){
$this->writeEvents[$key]->free();
unset($this->writeEvents[$key],$this->writeListeners[$key],$this->writeRefs[$key]);
}
}
function addTimer($interval,$callback){
$timer=new Timer($interval,$callback,false);
$this->scheduleTimer($timer);
return$timer;
}
function addPeriodicTimer($interval,$callback){
$timer=new Timer($interval,$callback,true);
$this->scheduleTimer($timer);
return$timer;
}
function cancelTimer(TimerInterface$timer){
if($this->timerEvents->contains($timer)){
$this->timerEvents[$timer]->free();
$this->timerEvents->detach($timer);
}
}
function futureTick($listener){
$this->futureTickQueue->add($listener);
}
function addSignal($signal,$listener){
$this->signals->add($signal,$listener);
if(!isset($this->signalEvents[$signal])){
$this->signalEvents[$signal]=Event::signal($this->eventBase,$signal,array($this->signals,'call'));
$this->signalEvents[$signal]->add();
}
}
function removeSignal($signal,$listener){
$this->signals->remove($signal,$listener);
if(isset($this->signalEvents[$signal])&&$this->signals->count($signal)===0){
$this->signalEvents[$signal]->free();
unset($this->signalEvents[$signal]);
}
}
function run(){
$this->running=true;
while($this->running){
$this->futureTickQueue->tick();
$flags=EventBase::LOOP_ONCE;
if(!$this->running||!$this->futureTickQueue->isEmpty()){
$flags|=EventBase::LOOP_NONBLOCK;
}elseif(!$this->readEvents&&!$this->writeEvents&&!$this->timerEvents->count()&&$this->signals->isEmpty()){
break;
}
$this->eventBase->loop($flags);
}
}
function stop(){
$this->running=false;
}
private function scheduleTimer(TimerInterface$timer){
$flags=Event::TIMEOUT;
if($timer->isPeriodic()){
$flags|=Event::PERSIST;
}
$event=new Event($this->eventBase,-1,$flags,$this->timerCallback,$timer);
$this->timerEvents[$timer]=$event;
$event->add($timer->getInterval());
}
private function createTimerCallback(){
$timers=$this->timerEvents;
$this->timerCallback=function($_,$__,$timer)use($timers){
\call_user_func($timer->getCallback(),$timer);
if(!$timer->isPeriodic()&&$timers->contains($timer)){
$this->cancelTimer($timer);
}
};
}
private function createStreamCallback(){
$read=&$this->readListeners;
$write=&$this->writeListeners;
$this->streamCallback=function($stream,$flags)use(&$read,&$write){
$key=(int)$stream;
if(Event::READ===(Event::READ&$flags)&&isset($read[$key])){
\call_user_func($read[$key],$stream);
}
if(Event::WRITE===(Event::WRITE&$flags)&&isset($write[$key])){
\call_user_func($write[$key],$stream);
}
};
}
}
namespace React\EventLoop;
use BadMethodCallException;
use libev\EventLoop;
use libev\IOEvent;
use libev\SignalEvent;
use libev\TimerEvent;
use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Timer\Timer;
use SplObjectStorage;
final class ExtLibevLoop implements LoopInterface
{
private$loop;
private$futureTickQueue;
private$timerEvents;
private$readEvents=array();
private$writeEvents=array();
private$running;
private$signals;
private$signalEvents=array();
function __construct(){
if(!\class_exists('libev\EventLoop',false)){
throw new BadMethodCallException('Cannot create ExtLibevLoop, ext-libev extension missing');
}
$this->loop=new EventLoop;
$this->futureTickQueue=new FutureTickQueue;
$this->timerEvents=new SplObjectStorage;
$this->signals=new SignalsHandler;
}
function addReadStream($stream,$listener){
if(isset($this->readEvents[(int)$stream])){
return;
}
$callback=function()use($stream,$listener){
\call_user_func($listener,$stream);
};
$event=new IOEvent($callback,$stream,IOEvent::READ);
$this->loop->add($event);
$this->readEvents[(int)$stream]=$event;
}
function addWriteStream($stream,$listener){
if(isset($this->writeEvents[(int)$stream])){
return;
}
$callback=function()use($stream,$listener){
\call_user_func($listener,$stream);
};
$event=new IOEvent($callback,$stream,IOEvent::WRITE);
$this->loop->add($event);
$this->writeEvents[(int)$stream]=$event;
}
function removeReadStream($stream){
$key=(int)$stream;
if(isset($this->readEvents[$key])){
$this->readEvents[$key]->stop();
$this->loop->remove($this->readEvents[$key]);
unset($this->readEvents[$key]);
}
}
function removeWriteStream($stream){
$key=(int)$stream;
if(isset($this->writeEvents[$key])){
$this->writeEvents[$key]->stop();
$this->loop->remove($this->writeEvents[$key]);
unset($this->writeEvents[$key]);
}
}
function addTimer($interval,$callback){
$timer=new Timer($interval,$callback,false);
$that=$this;
$timers=$this->timerEvents;
$callback=function()use($timer,$timers,$that){
\call_user_func($timer->getCallback(),$timer);
if($timers->contains($timer)){
$that->cancelTimer($timer);
}
};
$event=new TimerEvent($callback,$timer->getInterval());
$this->timerEvents->attach($timer,$event);
$this->loop->add($event);
return$timer;
}
function addPeriodicTimer($interval,$callback){
$timer=new Timer($interval,$callback,true);
$callback=function()use($timer){
\call_user_func($timer->getCallback(),$timer);
};
$event=new TimerEvent($callback,$interval,$interval);
$this->timerEvents->attach($timer,$event);
$this->loop->add($event);
return$timer;
}
function cancelTimer(TimerInterface$timer){
if(isset($this->timerEvents[$timer])){
$this->loop->remove($this->timerEvents[$timer]);
$this->timerEvents->detach($timer);
}
}
function futureTick($listener){
$this->futureTickQueue->add($listener);
}
function addSignal($signal,$listener){
$this->signals->add($signal,$listener);
if(!isset($this->signalEvents[$signal])){
$signals=$this->signals;
$this->signalEvents[$signal]=new SignalEvent(function()use($signals,$signal){
$signals->call($signal);
},$signal);
$this->loop->add($this->signalEvents[$signal]);
}
}
function removeSignal($signal,$listener){
$this->signals->remove($signal,$listener);
if(isset($this->signalEvents[$signal])&&$this->signals->count($signal)===0){
$this->signalEvents[$signal]->stop();
$this->loop->remove($this->signalEvents[$signal]);
unset($this->signalEvents[$signal]);
}
}
function run(){
$this->running=true;
while($this->running){
$this->futureTickQueue->tick();
$flags=EventLoop::RUN_ONCE;
if(!$this->running||!$this->futureTickQueue->isEmpty()){
$flags|=EventLoop::RUN_NOWAIT;
}elseif(!$this->readEvents&&!$this->writeEvents&&!$this->timerEvents->count()&&$this->signals->isEmpty()){
break;
}
$this->loop->run($flags);
}
}
function stop(){
$this->running=false;
}
}
namespace React\EventLoop;
use BadMethodCallException;
use Event;
use EventBase;
use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Timer\Timer;
use SplObjectStorage;
final class ExtLibeventLoop implements LoopInterface
{
const MICROSECONDS_PER_SECOND=1000000;
private$eventBase;
private$futureTickQueue;
private$timerCallback;
private$timerEvents;
private$streamCallback;
private$readEvents=array();
private$writeEvents=array();
private$readListeners=array();
private$writeListeners=array();
private$running;
private$signals;
private$signalEvents=array();
function __construct(){
if(!\function_exists('event_base_new')){
throw new BadMethodCallException('Cannot create ExtLibeventLoop, ext-libevent extension missing');
}
$this->eventBase=\event_base_new();
$this->futureTickQueue=new FutureTickQueue;
$this->timerEvents=new SplObjectStorage;
$this->signals=new SignalsHandler;
$this->createTimerCallback();
$this->createStreamCallback();
}
function addReadStream($stream,$listener){
$key=(int)$stream;
if(isset($this->readListeners[$key])){
return;
}
$event=\event_new();
\event_set($event,$stream,\EV_PERSIST|\EV_READ,$this->streamCallback);
\event_base_set($event,$this->eventBase);
\event_add($event);
$this->readEvents[$key]=$event;
$this->readListeners[$key]=$listener;
}
function addWriteStream($stream,$listener){
$key=(int)$stream;
if(isset($this->writeListeners[$key])){
return;
}
$event=\event_new();
\event_set($event,$stream,\EV_PERSIST|\EV_WRITE,$this->streamCallback);
\event_base_set($event,$this->eventBase);
\event_add($event);
$this->writeEvents[$key]=$event;
$this->writeListeners[$key]=$listener;
}
function removeReadStream($stream){
$key=(int)$stream;
if(isset($this->readListeners[$key])){
$event=$this->readEvents[$key];
\event_del($event);
\event_free($event);
unset($this->readEvents[$key],$this->readListeners[$key]);
}
}
function removeWriteStream($stream){
$key=(int)$stream;
if(isset($this->writeListeners[$key])){
$event=$this->writeEvents[$key];
\event_del($event);
\event_free($event);
unset($this->writeEvents[$key],$this->writeListeners[$key]);
}
}
function addTimer($interval,$callback){
$timer=new Timer($interval,$callback,false);
$this->scheduleTimer($timer);
return$timer;
}
function addPeriodicTimer($interval,$callback){
$timer=new Timer($interval,$callback,true);
$this->scheduleTimer($timer);
return$timer;
}
function cancelTimer(TimerInterface$timer){
if($this->timerEvents->contains($timer)){
$event=$this->timerEvents[$timer];
\event_del($event);
\event_free($event);
$this->timerEvents->detach($timer);
}
}
function futureTick($listener){
$this->futureTickQueue->add($listener);
}
function addSignal($signal,$listener){
$this->signals->add($signal,$listener);
if(!isset($this->signalEvents[$signal])){
$this->signalEvents[$signal]=\event_new();
\event_set($this->signalEvents[$signal],$signal,\EV_PERSIST|\EV_SIGNAL,array($this->signals,'call'));
\event_base_set($this->signalEvents[$signal],$this->eventBase);
\event_add($this->signalEvents[$signal]);
}
}
function removeSignal($signal,$listener){
$this->signals->remove($signal,$listener);
if(isset($this->signalEvents[$signal])&&$this->signals->count($signal)===0){
\event_del($this->signalEvents[$signal]);
\event_free($this->signalEvents[$signal]);
unset($this->signalEvents[$signal]);
}
}
function run(){
$this->running=true;
while($this->running){
$this->futureTickQueue->tick();
$flags=\EVLOOP_ONCE;
if(!$this->running||!$this->futureTickQueue->isEmpty()){
$flags|=\EVLOOP_NONBLOCK;
}elseif(!$this->readEvents&&!$this->writeEvents&&!$this->timerEvents->count()&&$this->signals->isEmpty()){
break;
}
\event_base_loop($this->eventBase,$flags);
}
}
function stop(){
$this->running=false;
}
private function scheduleTimer(TimerInterface$timer){
$this->timerEvents[$timer]=$event=\event_timer_new();
\event_timer_set($event,$this->timerCallback,$timer);
\event_base_set($event,$this->eventBase);
\event_add($event,$timer->getInterval()*self::MICROSECONDS_PER_SECOND);
}
private function createTimerCallback(){
$that=$this;
$timers=$this->timerEvents;
$this->timerCallback=function($_,$__,$timer)use($timers,$that){
\call_user_func($timer->getCallback(),$timer);
if(!$timers->contains($timer)){
return;
}
if($timer->isPeriodic()){
\event_add($timers[$timer],$timer->getInterval()*ExtLibeventLoop::MICROSECONDS_PER_SECOND
);
}else{
$that->cancelTimer($timer);
}
};
}
private function createStreamCallback(){
$read=&$this->readListeners;
$write=&$this->writeListeners;
$this->streamCallback=function($stream,$flags)use(&$read,&$write){
$key=(int)$stream;
if(\EV_READ===(\EV_READ&$flags)&&isset($read[$key])){
\call_user_func($read[$key],$stream);
}
if(\EV_WRITE===(\EV_WRITE&$flags)&&isset($write[$key])){
\call_user_func($write[$key],$stream);
}
};
}
}
namespace React\EventLoop;
final class Factory
{
static function create(){
if(\class_exists('libev\EventLoop',false)){
return new ExtLibevLoop;
}elseif(\class_exists('EvLoop',false)){
return new ExtEvLoop;
}elseif(\class_exists('EventBase',false)){
return new ExtEventLoop;
}elseif(\function_exists('event_base_new')&&\PHP_VERSION_ID<70000){
return new ExtLibeventLoop;
}
return new StreamSelectLoop;
}
}
namespace React\EventLoop;
final class SignalsHandler
{
private$signals=array();
function add($signal,$listener){
if(!isset($this->signals[$signal])){
$this->signals[$signal]=array();
}
if(\in_array($listener,$this->signals[$signal])){
return;
}
$this->signals[$signal][]=$listener;
}
function remove($signal,$listener){
if(!isset($this->signals[$signal])){
return;
}
$index=\array_search($listener,$this->signals[$signal],true);
unset($this->signals[$signal][$index]);
if(isset($this->signals[$signal])&&\count($this->signals[$signal])===0){
unset($this->signals[$signal]);
}
}
function call($signal){
if(!isset($this->signals[$signal])){
return;
}
foreach($this->signals[$signal]as$listener){
\call_user_func($listener,$signal);
}
}
function count($signal){
if(!isset($this->signals[$signal])){
return 0;
}
return\count($this->signals[$signal]);
}
function isEmpty(){
return!$this->signals;
}
}
namespace React\EventLoop;
use React\EventLoop\Signal\Pcntl;
use React\EventLoop\Tick\FutureTickQueue;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\Timers;
final class StreamSelectLoop implements LoopInterface
{
const MICROSECONDS_PER_SECOND=1000000;
private$futureTickQueue;
private$timers;
private$readStreams=array();
private$readListeners=array();
private$writeStreams=array();
private$writeListeners=array();
private$running;
private$pcntl=false;
private$signals;
function __construct(){
$this->futureTickQueue=new FutureTickQueue;
$this->timers=new Timers;
$this->pcntl=\extension_loaded('pcntl');
$this->signals=new SignalsHandler;
}
function addReadStream($stream,$listener){
$key=(int)$stream;
if(!isset($this->readStreams[$key])){
$this->readStreams[$key]=$stream;
$this->readListeners[$key]=$listener;
}
}
function addWriteStream($stream,$listener){
$key=(int)$stream;
if(!isset($this->writeStreams[$key])){
$this->writeStreams[$key]=$stream;
$this->writeListeners[$key]=$listener;
}
}
function removeReadStream($stream){
$key=(int)$stream;
unset($this->readStreams[$key],$this->readListeners[$key]);
}
function removeWriteStream($stream){
$key=(int)$stream;
unset($this->writeStreams[$key],$this->writeListeners[$key]);
}
function addTimer($interval,$callback){
$timer=new Timer($interval,$callback,false);
$this->timers->add($timer);
return$timer;
}
function addPeriodicTimer($interval,$callback){
$timer=new Timer($interval,$callback,true);
$this->timers->add($timer);
return$timer;
}
function cancelTimer(TimerInterface$timer){
$this->timers->cancel($timer);
}
function futureTick($listener){
$this->futureTickQueue->add($listener);
}
function addSignal($signal,$listener){
if($this->pcntl===false){
throw new\BadMethodCallException('Event loop feature "signals" isn\'t supported by the "StreamSelectLoop"');
}
$first=$this->signals->count($signal)===0;
$this->signals->add($signal,$listener);
if($first){
\pcntl_signal($signal,array($this->signals,'call'));
}
}
function removeSignal($signal,$listener){
if(!$this->signals->count($signal)){
return;
}
$this->signals->remove($signal,$listener);
if($this->signals->count($signal)===0){
\pcntl_signal($signal,\SIG_DFL);
}
}
function run(){
$this->running=true;
while($this->running){
$this->futureTickQueue->tick();
$this->timers->tick();
if(!$this->running||!$this->futureTickQueue->isEmpty()){
$timeout=0;
}elseif($scheduledAt=$this->timers->getFirst()){
$timeout=$scheduledAt-$this->timers->getTime();
if($timeout<0){
$timeout=0;
}else{
$timeout*=self::MICROSECONDS_PER_SECOND;
$timeout=$timeout>\PHP_INT_MAX?\PHP_INT_MAX:(int)$timeout;
}
}elseif($this->readStreams||$this->writeStreams||!$this->signals->isEmpty()){
$timeout=null;
}else{
break;
}
$this->waitForStreamActivity($timeout);
}
}
function stop(){
$this->running=false;
}
private function waitForStreamActivity($timeout){
$read=$this->readStreams;
$write=$this->writeStreams;
$available=$this->streamSelect($read,$write,$timeout);
if($this->pcntl){
\pcntl_signal_dispatch();
}
if(false===$available){
return;
}
foreach($read as$stream){
$key=(int)$stream;
if(isset($this->readListeners[$key])){
\call_user_func($this->readListeners[$key],$stream);
}
}
foreach($write as$stream){
$key=(int)$stream;
if(isset($this->writeListeners[$key])){
\call_user_func($this->writeListeners[$key],$stream);
}
}
}
private function streamSelect(array&$read,array&$write,$timeout){
if($read||$write){
$except=null;
return@\stream_select($read,$write,$except,$timeout===null?null:0,$timeout);
}
$timeout&&\usleep($timeout);
return 0;
}
}
namespace React\EventLoop\Tick;
use SplQueue;
final class FutureTickQueue
{
private$queue;
function __construct(){
$this->queue=new SplQueue;
}
function add($listener){
$this->queue->enqueue($listener);
}
function tick(){
$count=$this->queue->count();
while($count--){
\call_user_func($this->queue->dequeue());
}
}
function isEmpty(){
return$this->queue->isEmpty();
}
}
namespace React\EventLoop;
interface TimerInterface
{
function getInterval();
function getCallback();
function isPeriodic();
}
namespace React\EventLoop\Timer;
use React\EventLoop\TimerInterface;
final class Timer implements TimerInterface
{
const MIN_INTERVAL=0.000001;
private$interval;
private$callback;
private$periodic;
function __construct($interval,$callback,$periodic=false){
if($interval<self::MIN_INTERVAL){
$interval=self::MIN_INTERVAL;
}
$this->interval=(float)$interval;
$this->callback=$callback;
$this->periodic=(bool)$periodic;
}
function getInterval(){
return$this->interval;
}
function getCallback(){
return$this->callback;
}
function isPeriodic(){
return$this->periodic;
}
}
namespace React\EventLoop\Timer;
use React\EventLoop\TimerInterface;
final class Timers
{
private$time;
private$timers=array();
private$schedule=array();
private$sorted=true;
function updateTime(){
return$this->time=\microtime(true);
}
function getTime(){
return$this->time?:$this->updateTime();
}
function add(TimerInterface$timer){
$id=\spl_object_hash($timer);
$this->timers[$id]=$timer;
$this->schedule[$id]=$timer->getInterval()+\microtime(true);
$this->sorted=false;
}
function contains(TimerInterface$timer){
return isset($this->timers[\spl_object_hash($timer)]);
}
function cancel(TimerInterface$timer){
$id=\spl_object_hash($timer);
unset($this->timers[$id],$this->schedule[$id]);
}
function getFirst(){
if(!$this->sorted){
$this->sorted=true;
\asort($this->schedule);
}
return\reset($this->schedule);
}
function isEmpty(){
return\count($this->timers)===0;
}
function tick(){
if(!$this->sorted){
$this->sorted=true;
\asort($this->schedule);
}
$time=$this->updateTime();
foreach($this->schedule as$id=>$scheduled){
if($scheduled>=$time){
break;
}
if(!isset($this->schedule[$id])||$this->schedule[$id]!==$scheduled){
continue;
}
$timer=$this->timers[$id];
\call_user_func($timer->getCallback(),$timer);
if($timer->isPeriodic()&&isset($this->timers[$id])){
$this->schedule[$id]=$timer->getInterval()+$time;
$this->sorted=false;
}else{
unset($this->timers[$id],$this->schedule[$id]);
}
}
}
}
namespace React\Stream;
use Evenement\EventEmitterInterface;
interface ReadableStreamInterface extends EventEmitterInterface
{
function isReadable();
function pause();
function resume();
function pipe(WritableStreamInterface$dest,array$options=array());
function close();
}
namespace React\HttpClient;
use Evenement\EventEmitter;
use Exception;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;
class ChunkedStreamDecoder extends EventEmitter implements ReadableStreamInterface
{
const CRLF="\r\n";
protected$buffer='';
protected$remainingLength=0;
protected$nextChunkIsLength=true;
protected$stream;
protected$closed=false;
protected$reachedEnd=false;
function __construct(ReadableStreamInterface$stream){
$this->stream=$stream;
$this->stream->on('data',array($this,'handleData'));
$this->stream->on('end',array($this,'handleEnd'));
Util::forwardEvents($this->stream,$this,array('error',));
}
function handleData($data){
$this->buffer.=$data;
do{
$bufferLength=strlen($this->buffer);
$continue=$this->iterateBuffer();
$iteratedBufferLength=strlen($this->buffer);
}while($continue&&
$bufferLength!==$iteratedBufferLength&&
$iteratedBufferLength>0
);
if($this->buffer===false){
$this->buffer='';
}
}
protected function iterateBuffer(){
if(strlen($this->buffer)<=1){
return false;
}
if($this->nextChunkIsLength){
$crlfPosition=strpos($this->buffer,static::CRLF);
if($crlfPosition===false&&strlen($this->buffer)>1024){
$this->emit('error',array(new Exception('Chunk length header longer then 1024 bytes'),));
$this->close();
return false;
}
if($crlfPosition===false){
return false;}
$lengthChunk=substr($this->buffer,0,$crlfPosition);
if(strpos($lengthChunk,';')!==false){
list($lengthChunk)=explode(';',$lengthChunk,2);
}
if($lengthChunk!==''){
$lengthChunk=ltrim(trim($lengthChunk),"0");
if($lengthChunk===''){
$this->reachedEnd=true;
$this->emit('end');
$this->close();
return false;
}
}
$this->nextChunkIsLength=false;
if(dechex(hexdec($lengthChunk))!==strtolower($lengthChunk)){
$this->emit('error',array(new Exception('Unable to validate "'.$lengthChunk.'" as chunk length header'),));
$this->close();
return false;
}
$this->remainingLength=hexdec($lengthChunk);
$this->buffer=substr($this->buffer,$crlfPosition+2);
return true;
}
if($this->remainingLength>0){
$chunkLength=$this->getChunkLength();
if($chunkLength===0){
return true;
}
$this->emit('data',array(substr($this->buffer,0,$chunkLength),$this
));
$this->remainingLength-=$chunkLength;
$this->buffer=substr($this->buffer,$chunkLength);
return true;
}
$this->nextChunkIsLength=true;
$this->buffer=substr($this->buffer,2);
return true;
}
protected function getChunkLength(){
$bufferLength=strlen($this->buffer);
if($bufferLength>=$this->remainingLength){
return$this->remainingLength;
}
return$bufferLength;
}
function pause(){
$this->stream->pause();
}
function resume(){
$this->stream->resume();
}
function isReadable(){
return$this->stream->isReadable();
}
function pipe(WritableStreamInterface$dest,array$options=array()){
Util::pipe($this,$dest,$options);
return$dest;
}
function close(){
$this->closed=true;
return$this->stream->close();
}
function handleEnd(){
$this->handleData('');
if($this->closed){
return;
}
if($this->buffer===''&&$this->reachedEnd){
$this->emit('end');
$this->close();
return;
}
$this->emit('error',array(new Exception('Stream ended with incomplete control code')));
$this->close();
}
}
namespace React\HttpClient;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectorInterface;
use React\Socket\Connector;
class Client
{
private$connector;
function __construct(LoopInterface$loop,ConnectorInterface$connector=null){
if($connector===null){
$connector=new Connector($loop);
}
$this->connector=$connector;
}
function request($method,$url,array$headers=array(),$protocolVersion='1.0'){
$requestData=new RequestData($method,$url,$headers,$protocolVersion);
return new Request($this->connector,$requestData);
}
}
namespace React\Stream;
use Evenement\EventEmitterInterface;
interface WritableStreamInterface extends EventEmitterInterface
{
function isWritable();
function write($data);
function end($data=null);
function close();
}
namespace React\HttpClient;
use Evenement\EventEmitter;
use React\Promise;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;
use React\Stream\WritableStreamInterface;
use RingCentral\Psr7 as gPsr;
class Request extends EventEmitter implements WritableStreamInterface
{
const STATE_INIT=0;
const STATE_WRITING_HEAD=1;
const STATE_HEAD_WRITTEN=2;
const STATE_END=3;
private$connector;
private$requestData;
private$stream;
private$buffer;
private$responseFactory;
private$state=self::STATE_INIT;
private$ended=false;
private$pendingWrites='';
function __construct(ConnectorInterface$connector,RequestData$requestData){
$this->connector=$connector;
$this->requestData=$requestData;
}
function isWritable(){
return self::STATE_END>$this->state&&!$this->ended;
}
private function writeHead(){
$this->state=self::STATE_WRITING_HEAD;
$requestData=$this->requestData;
$streamRef=&$this->stream;
$stateRef=&$this->state;
$pendingWrites=&$this->pendingWrites;
$that=$this;
$promise=$this->connect();
$promise->then(function(ConnectionInterface$stream)use($requestData,&$streamRef,&$stateRef,&$pendingWrites,$that){
$streamRef=$stream;
$stream->on('drain',array($that,'handleDrain'));
$stream->on('data',array($that,'handleData'));
$stream->on('end',array($that,'handleEnd'));
$stream->on('error',array($that,'handleError'));
$stream->on('close',array($that,'handleClose'));
$headers=(string)$requestData;
$more=$stream->write($headers.$pendingWrites);
$stateRef=Request::STATE_HEAD_WRITTEN;
if($pendingWrites!==''){
$pendingWrites='';
if($more){
$that->emit('drain');
}
}
},array($this,'closeError'));
$this->on('close',function()use($promise){
$promise->cancel();
});
}
function write($data){
if(!$this->isWritable()){
return false;
}
if(self::STATE_HEAD_WRITTEN<=$this->state){
return$this->stream->write($data);
}
$this->pendingWrites.=$data;
if(self::STATE_WRITING_HEAD>$this->state){
$this->writeHead();
}
return false;
}
function end($data=null){
if(!$this->isWritable()){
return;
}
if(null!==$data){
$this->write($data);
}else if(self::STATE_WRITING_HEAD>$this->state){
$this->writeHead();
}
$this->ended=true;
}
function handleDrain(){
$this->emit('drain');
}
function handleData($data){
$this->buffer.=$data;
if(false!==strpos($this->buffer,"\r\n\r\n")||false!==strpos($this->buffer,"\n\n")){
try{
list($response,$bodyChunk)=$this->parseResponse($this->buffer);
}catch(\InvalidArgumentException$exception){
$this->emit('error',array($exception));
}
$this->buffer=null;
$this->stream->removeListener('drain',array($this,'handleDrain'));
$this->stream->removeListener('data',array($this,'handleData'));
$this->stream->removeListener('end',array($this,'handleEnd'));
$this->stream->removeListener('error',array($this,'handleError'));
$this->stream->removeListener('close',array($this,'handleClose'));
if(!isset($response)){
return;
}
$response->on('close',array($this,'close'));
$that=$this;
$response->on('error',function(\Exception$error)use($that){
$that->closeError(new\RuntimeException("An error occured in the response",0,$error
));
});
$this->emit('response',array($response,$this));
$this->stream->emit('data',array($bodyChunk));
}
}
function handleEnd(){
$this->closeError(new\RuntimeException("Connection ended before receiving response"));
}
function handleError(\Exception$error){
$this->closeError(new\RuntimeException("An error occurred in the underlying stream",0,$error
));
}
function handleClose(){
$this->close();
}
function closeError(\Exception$error){
if(self::STATE_END<=$this->state){
return;
}
$this->emit('error',array($error));
$this->close();
}
function close(){
if(self::STATE_END<=$this->state){
return;
}
$this->state=self::STATE_END;
$this->pendingWrites='';
if($this->stream){
$this->stream->close();
}
$this->emit('close');
$this->removeAllListeners();
}
protected function parseResponse($data){
$psrResponse=gPsr\parse_response($data);
$headers=array_map(function($val){
if(1===count($val)){
$val=$val[0];
}
return$val;
},$psrResponse->getHeaders());
$factory=$this->getResponseFactory();
$response=$factory('HTTP',$psrResponse->getProtocolVersion(),$psrResponse->getStatusCode(),$psrResponse->getReasonPhrase(),$headers
);
return array($response,(string)($psrResponse->getBody()));
}
protected function connect(){
$scheme=$this->requestData->getScheme();
if($scheme!=='https'&&$scheme!=='http'){
return Promise\reject(new\InvalidArgumentException('Invalid request URL given'));
}
$host=$this->requestData->getHost();
$port=$this->requestData->getPort();
if($scheme==='https'){
$host='tls://'.$host;
}
return$this->connector
->connect($host.':'.$port);
}
function setResponseFactory($factory){
$this->responseFactory=$factory;
}
function getResponseFactory(){
if(null===$factory=$this->responseFactory){
$stream=$this->stream;
$factory=function($protocol,$version,$code,$reasonPhrase,$headers)use($stream){
return new Response($stream,$protocol,$version,$code,$reasonPhrase,$headers
);
};
$this->responseFactory=$factory;
}
return$factory;
}
}
namespace React\HttpClient;
class RequestData
{
private$method;
private$url;
private$headers;
private$protocolVersion;
function __construct($method,$url,array$headers=array(),$protocolVersion='1.0'){
$this->method=$method;
$this->url=$url;
$this->headers=$headers;
$this->protocolVersion=$protocolVersion;
}
private function mergeDefaultheaders(array$headers){
$port=($this->getDefaultPort()===$this->getPort())?'':":{$this->getPort()}";
$connectionHeaders=('1.1'===$this->protocolVersion)?array('Connection'=>'close'):array();
$authHeaders=$this->getAuthHeaders();
$defaults=array_merge(array('Host'=>$this->getHost().$port,'User-Agent'=>'React/alpha',),$connectionHeaders,$authHeaders
);
$lower=array_change_key_case($headers,CASE_LOWER);
foreach($defaults as$key=>$_){
if(isset($lower[strtolower($key)])){
unset($defaults[$key]);
}
}
return array_merge($defaults,$headers);
}
function getScheme(){
return parse_url($this->url,PHP_URL_SCHEME);
}
function getHost(){
return parse_url($this->url,PHP_URL_HOST);
}
function getPort(){
return(int)parse_url($this->url,PHP_URL_PORT)?:$this->getDefaultPort();
}
function getDefaultPort(){
return('https'===$this->getScheme())?443:80;
}
function getPath(){
$path=parse_url($this->url,PHP_URL_PATH);
$queryString=parse_url($this->url,PHP_URL_QUERY);
if($path===null){
$path=($this->method==='OPTIONS'&&$queryString===null)?'*':'/';
}
if($queryString!==null){
$path.='?'.$queryString;
}
return$path;
}
function setProtocolVersion($version){
$this->protocolVersion=$version;
}
function __toString(){
$headers=$this->mergeDefaultheaders($this->headers);
$data='';
$data.="{$this->method} {$this->getPath()} HTTP/{$this->protocolVersion}\r\n";
foreach($headers as$name=>$values){
foreach((array)$values as$value){
$data.="$name: $value\r\n";
}
}
$data.="\r\n";
return$data;
}
private function getUrlUserPass(){
$components=parse_url($this->url);
if(isset($components['user'])){
return array('user'=>$components['user'],'pass'=>isset($components['pass'])?$components['pass']:null,);
}
}
private function getAuthHeaders(){
if(null!==$auth=$this->getUrlUserPass()){
return array('Authorization'=>'Basic '.base64_encode($auth['user'].':'.$auth['pass']),);
}
return array();
}
}
namespace React\HttpClient;
use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;
class Response extends EventEmitter implements ReadableStreamInterface
{
private$stream;
private$protocol;
private$version;
private$code;
private$reasonPhrase;
private$headers;
private$readable=true;
function __construct(ReadableStreamInterface$stream,$protocol,$version,$code,$reasonPhrase,$headers){
$this->stream=$stream;
$this->protocol=$protocol;
$this->version=$version;
$this->code=$code;
$this->reasonPhrase=$reasonPhrase;
$this->headers=$headers;
if(strtolower($this->getHeaderLine('Transfer-Encoding'))==='chunked'){
$this->stream=new ChunkedStreamDecoder($stream);
$this->removeHeader('Transfer-Encoding');
}
$this->stream->on('data',array($this,'handleData'));
$this->stream->on('error',array($this,'handleError'));
$this->stream->on('end',array($this,'handleEnd'));
$this->stream->on('close',array($this,'handleClose'));
}
function getProtocol(){
return$this->protocol;
}
function getVersion(){
return$this->version;
}
function getCode(){
return$this->code;
}
function getReasonPhrase(){
return$this->reasonPhrase;
}
function getHeaders(){
return$this->headers;
}
private function removeHeader($name){
foreach($this->headers as$key=>$value){
if(strcasecmp($name,$key)===0){
unset($this->headers[$key]);
break;
}
}
}
private function getHeader($name){
$name=strtolower($name);
$normalized=array_change_key_case($this->headers,CASE_LOWER);
return isset($normalized[$name])?(array)$normalized[$name]:array();
}
private function getHeaderLine($name){
return join(', ',$this->getHeader($name));
}
function handleData($data){
if($this->readable){
$this->emit('data',array($data));
}
}
function handleEnd(){
if(!$this->readable){
return;
}
$this->emit('end');
$this->close();
}
function handleError(\Exception$error){
if(!$this->readable){
return;
}
$this->emit('error',array(new\RuntimeException("An error occurred in the underlying stream",0,$error
)));
$this->close();
}
function handleClose(){
$this->close();
}
function close(){
if(!$this->readable){
return;
}
$this->readable=false;
$this->stream->close();
$this->emit('close');
$this->removeAllListeners();
}
function isReadable(){
return$this->readable;
}
function pause(){
if(!$this->readable){
return;
}
$this->stream->pause();
}
function resume(){
if(!$this->readable){
return;
}
$this->stream->resume();
}
function pipe(WritableStreamInterface$dest,array$options=array()){
Util::pipe($this,$dest,$options);
return$dest;
}
}
namespace React\Http\Io;
use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;
use Exception;
class ChunkedDecoder extends EventEmitter implements ReadableStreamInterface
{
const CRLF="\r\n";
const MAX_CHUNK_HEADER_SIZE=1024;
private$closed=false;
private$input;
private$buffer='';
private$chunkSize=0;
private$transferredSize=0;
private$headerCompleted=false;
function __construct(ReadableStreamInterface$input){
$this->input=$input;
$this->input->on('data',array($this,'handleData'));
$this->input->on('end',array($this,'handleEnd'));
$this->input->on('error',array($this,'handleError'));
$this->input->on('close',array($this,'close'));
}
function isReadable(){
return!$this->closed&&$this->input->isReadable();
}
function pause(){
$this->input->pause();
}
function resume(){
$this->input->resume();
}
function pipe(WritableStreamInterface$dest,array$options=array()){
Util::pipe($this,$dest,$options);
return$dest;
}
function close(){
if($this->closed){
return;
}
$this->buffer='';
$this->closed=true;
$this->input->close();
$this->emit('close');
$this->removeAllListeners();
}
function handleEnd(){
if(!$this->closed){
$this->handleError(new Exception('Unexpected end event'));
}
}
function handleError(Exception$e){
$this->emit('error',array($e));
$this->close();
}
function handleData($data){
$this->buffer.=$data;
while($this->buffer!==''){
if(!$this->headerCompleted){
$positionCrlf=strpos($this->buffer,static::CRLF);
if($positionCrlf===false){
if(isset($this->buffer[static::MAX_CHUNK_HEADER_SIZE])){
$this->handleError(new Exception('Chunk header size inclusive extension bigger than'.static::MAX_CHUNK_HEADER_SIZE.' bytes'));
}
return;
}
$header=strtolower((string)substr($this->buffer,0,$positionCrlf));
$hexValue=$header;
if(strpos($header,';')!==false){
$array=explode(';',$header);
$hexValue=$array[0];
}
if($hexValue!==''){
$hexValue=ltrim($hexValue,"0");
if($hexValue===''){
$hexValue="0";
}
}
$this->chunkSize=hexdec($hexValue);
if(dechex($this->chunkSize)!==$hexValue){
$this->handleError(new Exception($hexValue.' is not a valid hexadecimal number'));
return;
}
$this->buffer=(string)substr($this->buffer,$positionCrlf+2);
$this->headerCompleted=true;
if($this->buffer===''){
return;
}
}
$chunk=(string)substr($this->buffer,0,$this->chunkSize-$this->transferredSize);
if($chunk!==''){
$this->transferredSize+=strlen($chunk);
$this->emit('data',array($chunk));
$this->buffer=(string)substr($this->buffer,strlen($chunk));
}
$positionCrlf=strpos($this->buffer,static::CRLF);
if($positionCrlf===0){
if($this->chunkSize===0){
$this->emit('end');
$this->close();
return;
}
$this->chunkSize=0;
$this->headerCompleted=false;
$this->transferredSize=0;
$this->buffer=(string)substr($this->buffer,2);
}
if($positionCrlf!==0&&$this->chunkSize===$this->transferredSize&&strlen($this->buffer)>2){
$this->handleError(new Exception('Chunk does not end with a CLRF'));
return;
}
if($positionCrlf!==0&&strlen($this->buffer)<2){
return;
}
}
}
}
namespace React\Http\Io;
use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;
class ChunkedEncoder extends EventEmitter implements ReadableStreamInterface
{
private$input;
private$closed;
function __construct(ReadableStreamInterface$input){
$this->input=$input;
$this->input->on('data',array($this,'handleData'));
$this->input->on('end',array($this,'handleEnd'));
$this->input->on('error',array($this,'handleError'));
$this->input->on('close',array($this,'close'));
}
function isReadable(){
return!$this->closed&&$this->input->isReadable();
}
function pause(){
$this->input->pause();
}
function resume(){
$this->input->resume();
}
function pipe(WritableStreamInterface$dest,array$options=array()){
Util::pipe($this,$dest,$options);
return$dest;
}
function close(){
if($this->closed){
return;
}
$this->closed=true;
$this->input->close();
$this->emit('close');
$this->removeAllListeners();
}
function handleData($data){
if($data===''){
return;
}
$completeChunk=$this->createChunk($data);
$this->emit('data',array($completeChunk));
}
function handleError(\Exception$e){
$this->emit('error',array($e));
$this->close();
}
function handleEnd(){
$this->emit('data',array("0\r\n\r\n"));
if(!$this->closed){
$this->emit('end');
$this->close();
}
}
private function createChunk($data){
$byteSize=dechex(strlen($data));
$chunkBeginning=$byteSize."\r\n";
return$chunkBeginning.$data."\r\n";
}
}
namespace React\Http\Io;
use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;
class CloseProtectionStream extends EventEmitter implements ReadableStreamInterface
{
private$input;
private$closed=false;
private$paused=false;
function __construct(ReadableStreamInterface$input){
$this->input=$input;
$this->input->on('data',array($this,'handleData'));
$this->input->on('end',array($this,'handleEnd'));
$this->input->on('error',array($this,'handleError'));
$this->input->on('close',array($this,'close'));
}
function isReadable(){
return!$this->closed&&$this->input->isReadable();
}
function pause(){
if($this->closed){
return;
}
$this->paused=true;
$this->input->pause();
}
function resume(){
if($this->closed){
return;
}
$this->paused=false;
$this->input->resume();
}
function pipe(WritableStreamInterface$dest,array$options=array()){
Util::pipe($this,$dest,$options);
return$dest;
}
function close(){
if($this->closed){
return;
}
$this->closed=true;
$this->input->removeListener('data',array($this,'handleData'));
$this->input->removeListener('error',array($this,'handleError'));
$this->input->removeListener('end',array($this,'handleEnd'));
$this->input->removeListener('close',array($this,'close'));
if($this->paused){
$this->paused=false;
$this->input->resume();
}
$this->emit('close');
$this->removeAllListeners();
}
function handleData($data){
$this->emit('data',array($data));
}
function handleEnd(){
$this->emit('end');
$this->close();
}
function handleError(\Exception$e){
$this->emit('error',array($e));
}
}
namespace React\Http\Io;
use Evenement\EventEmitter;
use Psr\Http\Message\StreamInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;
class HttpBodyStream extends EventEmitter implements StreamInterface,ReadableStreamInterface
{
var$input;
private$closed=false;
private$size;
function __construct(ReadableStreamInterface$input,$size){
$this->input=$input;
$this->size=$size;
$this->input->on('data',array($this,'handleData'));
$this->input->on('end',array($this,'handleEnd'));
$this->input->on('error',array($this,'handleError'));
$this->input->on('close',array($this,'close'));
}
function isReadable(){
return!$this->closed&&$this->input->isReadable();
}
function pause(){
$this->input->pause();
}
function resume(){
$this->input->resume();
}
function pipe(WritableStreamInterface$dest,array$options=array()){
Util::pipe($this,$dest,$options);
return$dest;
}
function close(){
if($this->closed){
return;
}
$this->closed=true;
$this->input->close();
$this->emit('close');
$this->removeAllListeners();
}
function getSize(){
return$this->size;
}
function __toString(){
return'';
}
function detach(){
return;
}
function tell(){
throw new\BadMethodCallException();
}
function eof(){
throw new\BadMethodCallException();
}
function isSeekable(){
return false;
}
function seek($offset,$whence=SEEK_SET){
throw new\BadMethodCallException();
}
function rewind(){
throw new\BadMethodCallException();
}
function isWritable(){
return false;
}
function write($string){
throw new\BadMethodCallException();
}
function read($length){
throw new\BadMethodCallException();
}
function getContents(){
return'';
}
function getMetadata($key=null){
return;
}
function handleData($data){
$this->emit('data',array($data));
}
function handleError(\Exception$e){
$this->emit('error',array($e));
$this->close();
}
function handleEnd(){
if(!$this->closed){
$this->emit('end');
$this->close();
}
}
}
namespace React\Http\Io;
final class IniUtil
{
static function iniSizeToBytes($size){
if(is_numeric($size)){
return(int)$size;
}
$suffix=strtoupper(substr($size,-1));
$strippedSize=substr($size,0,-1);
if(!is_numeric($strippedSize)){
throw new\InvalidArgumentException("$size is not a valid ini size");
}
if($strippedSize<=0){
throw new\InvalidArgumentException("Expect $size to be higher isn't zero or lower");
}
if($suffix==='K'){
return$strippedSize*1024;
}
if($suffix==='M'){
return$strippedSize*1024*1024;
}
if($suffix==='G'){
return$strippedSize*1024*1024*1024;
}
if($suffix==='T'){
return$strippedSize*1024*1024*1024*1024;
}
return(int)$size;
}
}
namespace React\Http\Io;
use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;
class LengthLimitedStream extends EventEmitter implements ReadableStreamInterface
{
private$stream;
private$closed=false;
private$transferredLength=0;
private$maxLength;
function __construct(ReadableStreamInterface$stream,$maxLength){
$this->stream=$stream;
$this->maxLength=$maxLength;
$this->stream->on('data',array($this,'handleData'));
$this->stream->on('end',array($this,'handleEnd'));
$this->stream->on('error',array($this,'handleError'));
$this->stream->on('close',array($this,'close'));
}
function isReadable(){
return!$this->closed&&$this->stream->isReadable();
}
function pause(){
$this->stream->pause();
}
function resume(){
$this->stream->resume();
}
function pipe(WritableStreamInterface$dest,array$options=array()){
Util::pipe($this,$dest,$options);
return$dest;
}
function close(){
if($this->closed){
return;
}
$this->closed=true;
$this->stream->close();
$this->emit('close');
$this->removeAllListeners();
}
function handleData($data){
if(($this->transferredLength+strlen($data))>$this->maxLength){
$data=(string)substr($data,0,$this->maxLength-$this->transferredLength);
}
if($data!==''){
$this->transferredLength+=strlen($data);
$this->emit('data',array($data));
}
if($this->transferredLength===$this->maxLength){
$this->emit('end');
$this->close();
$this->stream->removeListener('data',array($this,'handleData'));
}
}
function handleError(\Exception$e){
$this->emit('error',array($e));
$this->close();
}
function handleEnd(){
if(!$this->closed){
$this->handleError(new\Exception('Unexpected end event'));
}
}
}
namespace React\Http\Io;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\PromiseInterface;
final class MiddlewareRunner
{
private$middleware;
function __construct(array$middleware){
$this->middleware=array_values($middleware);
}
function __invoke(ServerRequestInterface$request){
if(empty($this->middleware)){
throw new\RuntimeException('No middleware to run');
}
return$this->call($request,0);
}
function call(ServerRequestInterface$request,$position){
if(!isset($this->middleware[$position+1])){
$handler=$this->middleware[$position];
return$handler($request);
}
$that=$this;
$next=function(ServerRequestInterface$request)use($that,$position){
return$that->call($request,$position+1);
};
$handler=$this->middleware[$position];
return$handler($request,$next);
}
}
namespace React\Http\Io;
use Psr\Http\Message\ServerRequestInterface;
use RingCentral\Psr7;
final class MultipartParser
{
private$request;
private$maxFileSize;
private$maxInputVars=1000;
private$maxInputNestingLevel=64;
private$uploadMaxFilesize;
private$maxFileUploads;
private$postCount=0;
private$filesCount=0;
private$emptyCount=0;
function __construct($uploadMaxFilesize=null,$maxFileUploads=null){
$var=ini_get('max_input_vars');
if($var!==false){
$this->maxInputVars=(int)$var;
}
$var=ini_get('max_input_nesting_level');
if($var!==false){
$this->maxInputNestingLevel=(int)$var;
}
if($uploadMaxFilesize===null){
$uploadMaxFilesize=ini_get('upload_max_filesize');
}
$this->uploadMaxFilesize=IniUtil::iniSizeToBytes($uploadMaxFilesize);
$this->maxFileUploads=$maxFileUploads===null?(ini_get('file_uploads')===''?0:(int)ini_get('max_file_uploads')):(int)$maxFileUploads;
}
function parse(ServerRequestInterface$request){
$contentType=$request->getHeaderLine('content-type');
if(!preg_match('/boundary="?(.*)"?$/',$contentType,$matches)){
return$request;
}
$this->request=$request;
$this->parseBody('--'.$matches[1],(string)$request->getBody());
$request=$this->request;
$this->request=null;
$this->postCount=0;
$this->filesCount=0;
$this->emptyCount=0;
$this->maxFileSize=null;
return$request;
}
private function parseBody($boundary,$buffer){
$len=strlen($boundary);
$start=strpos($buffer,$boundary."\r\n");
while($start!==false){
$start+=$len+2;
$end=strpos($buffer,"\r\n".$boundary,$start);
if($end===false){
break;
}
$this->parsePart(substr($buffer,$start,$end-$start));
$start=$end;
}
}
private function parsePart($chunk){
$pos=strpos($chunk,"\r\n\r\n");
if($pos===false){
return;
}
$headers=$this->parseHeaders((string)substr($chunk,0,$pos));
$body=(string)substr($chunk,$pos+4);
if(!isset($headers['content-disposition'])){
return;
}
$name=$this->getParameterFromHeader($headers['content-disposition'],'name');
if($name===null){
return;
}
$filename=$this->getParameterFromHeader($headers['content-disposition'],'filename');
if($filename!==null){
$this->parseFile($name,$filename,isset($headers['content-type'][0])?$headers['content-type'][0]:null,$body
);
}else{
$this->parsePost($name,$body);
}
}
private function parseFile($name,$filename,$contentType,$contents){
$file=$this->parseUploadedFile($filename,$contentType,$contents);
if($file===null){
return;
}
$this->request=$this->request->withUploadedFiles($this->extractPost($this->request->getUploadedFiles(),$name,$file
));
}
private function parseUploadedFile($filename,$contentType,$contents){
$size=strlen($contents);
if($size===0&&$filename===''){
if(++$this->emptyCount+$this->filesCount>$this->maxInputVars){
return;
}
return new UploadedFile(Psr7\stream_for(),$size,UPLOAD_ERR_NO_FILE,$filename,$contentType
);
}
if(++$this->filesCount>$this->maxFileUploads){
return;
}
if($size>$this->uploadMaxFilesize){
return new UploadedFile(Psr7\stream_for(),$size,UPLOAD_ERR_INI_SIZE,$filename,$contentType
);
}
if($this->maxFileSize!==null&&$size>$this->maxFileSize){
return new UploadedFile(Psr7\stream_for(),$size,UPLOAD_ERR_FORM_SIZE,$filename,$contentType
);
}
return new UploadedFile(Psr7\stream_for($contents),$size,UPLOAD_ERR_OK,$filename,$contentType
);
}
private function parsePost($name,$value){
if(++$this->postCount>$this->maxInputVars){
return;
}
$this->request=$this->request->withParsedBody($this->extractPost($this->request->getParsedBody(),$name,$value
));
if(strtoupper($name)==='MAX_FILE_SIZE'){
$this->maxFileSize=(int)$value;
if($this->maxFileSize===0){
$this->maxFileSize=null;
}
}
}
private function parseHeaders($header){
$headers=array();
foreach(explode("\r\n",trim($header))as$line){
$parts=explode(':',$line,2);
if(!isset($parts[1])){
continue;
}
$key=strtolower(trim($parts[0]));
$values=explode(';',$parts[1]);
$values=array_map('trim',$values);
$headers[$key]=$values;
}
return$headers;
}
private function getParameterFromHeader(array$header,$parameter){
foreach($header as$part){
if(preg_match('/'.$parameter.'="?(.*)"$/',$part,$matches)){
return$matches[1];
}
}
return;
}
private function extractPost($postFields,$key,$value){
$chunks=explode('[',$key);
if(count($chunks)==1){
$postFields[$key]=$value;
return$postFields;
}
if(isset($chunks[$this->maxInputNestingLevel])){
return$postFields;
}
$chunkKey=rtrim($chunks[0],']');
$parent=&$postFields;
for($i=1;isset($chunks[$i]);$i++){
$previousChunkKey=$chunkKey;
if($previousChunkKey===''){
$parent[]=array();
end($parent);
$parent=&$parent[key($parent)];
}else{
if(!isset($parent[$previousChunkKey])||!is_array($parent[$previousChunkKey])){
$parent[$previousChunkKey]=array();
}
$parent=&$parent[$previousChunkKey];
}
$chunkKey=rtrim($chunks[$i],']');
}
if($chunkKey===''){
$parent[]=$value;
}else{
$parent[$chunkKey]=$value;
}
return$postFields;
}
}
namespace React\Http\Io;
use Evenement\EventEmitter;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;
class PauseBufferStream extends EventEmitter implements ReadableStreamInterface
{
private$input;
private$closed=false;
private$paused=false;
private$dataPaused='';
private$endPaused=false;
private$closePaused=false;
private$errorPaused;
private$implicit=false;
function __construct(ReadableStreamInterface$input){
$this->input=$input;
$this->input->on('data',array($this,'handleData'));
$this->input->on('end',array($this,'handleEnd'));
$this->input->on('error',array($this,'handleError'));
$this->input->on('close',array($this,'handleClose'));
}
function pauseImplicit(){
$this->pause();
$this->implicit=true;
}
function resumeImplicit(){
if($this->implicit){
$this->resume();
}
}
function isReadable(){
return!$this->closed;
}
function pause(){
if($this->closed){
return;
}
$this->input->pause();
$this->paused=true;
$this->implicit=false;
}
function resume(){
if($this->closed){
return;
}
$this->paused=false;
$this->implicit=false;
if($this->dataPaused!==''){
$this->emit('data',array($this->dataPaused));
$this->dataPaused='';
}
if($this->errorPaused){
$this->emit('error',array($this->errorPaused));
return$this->close();
}
if($this->endPaused){
$this->endPaused=false;
$this->emit('end');
return$this->close();
}
if($this->closePaused){
$this->closePaused=false;
return$this->close();
}
$this->input->resume();
}
function pipe(WritableStreamInterface$dest,array$options=array()){
Util::pipe($this,$dest,$options);
return$dest;
}
function close(){
if($this->closed){
return;
}
$this->closed=true;
$this->dataPaused='';
$this->endPaused=$this->closePaused=false;
$this->errorPaused=null;
$this->input->close();
$this->emit('close');
$this->removeAllListeners();
}
function handleData($data){
if($this->paused){
$this->dataPaused.=$data;
return;
}
$this->emit('data',array($data));
}
function handleError(\Exception$e){
if($this->paused){
$this->errorPaused=$e;
return;
}
$this->emit('error',array($e));
$this->close();
}
function handleEnd(){
if($this->paused){
$this->endPaused=true;
return;
}
if(!$this->closed){
$this->emit('end');
$this->close();
}
}
function handleClose(){
if($this->paused){
$this->closePaused=true;
return;
}
$this->close();
}
}
namespace React\Http\Io;
use Evenement\EventEmitter;
use RingCentral\Psr7 as g7;
use Exception;
class RequestHeaderParser extends EventEmitter
{
private$buffer='';
private$maxSize=8192;
private$localSocketUri;
private$remoteSocketUri;
function __construct($localSocketUri=null,$remoteSocketUri=null){
$this->localSocketUri=$localSocketUri;
$this->remoteSocketUri=$remoteSocketUri;
}
function feed($data){
$this->buffer.=$data;
$endOfHeader=strpos($this->buffer,"\r\n\r\n");
if($endOfHeader>$this->maxSize||($endOfHeader===false&&isset($this->buffer[$this->maxSize]))){
$this->emit('error',array(new\OverflowException("Maximum header size of {$this->maxSize} exceeded.",431),$this));
$this->removeAllListeners();
return;
}
if(false!==$endOfHeader){
try{
$this->parseAndEmitRequest($endOfHeader);
}catch(Exception$exception){
$this->emit('error',array($exception));
}
$this->removeAllListeners();
}
}
private function parseAndEmitRequest($endOfHeader){
$request=$this->parseRequest((string)substr($this->buffer,0,$endOfHeader));
$bodyBuffer=isset($this->buffer[$endOfHeader+4])?substr($this->buffer,$endOfHeader+4):'';
$this->emit('headers',array($request,$bodyBuffer));
}
private function parseRequest($headers){
if(!preg_match('#^[^ ]+ [^ ]+ HTTP/\d\.\d#m',$headers)){
throw new\InvalidArgumentException('Unable to parse invalid request-line');
}
$originalTarget=null;
if(strncmp($headers,'OPTIONS * ',10)===0){
$originalTarget='*';
$headers='OPTIONS / '.substr($headers,10);
}elseif(strncmp($headers,'CONNECT ',8)===0){
$parts=explode(' ',$headers,3);
$uri=parse_url('tcp://'.$parts[1]);
if(isset($uri['scheme'],$uri['host'],$uri['port'])&&count($uri)===3){
$originalTarget=$parts[1];
$parts[1]='http://'.$parts[1].'/';
$headers=join(' ',$parts);
}else{
throw new\InvalidArgumentException('CONNECT method MUST use authority-form request target');
}
}
$request=g7\parse_request($headers);
$serverParams=array('REQUEST_TIME'=>time(),'REQUEST_TIME_FLOAT'=>microtime(true));
if($this->remoteSocketUri!==null){
$remoteAddress=parse_url($this->remoteSocketUri);
$serverParams['REMOTE_ADDR']=$remoteAddress['host'];
$serverParams['REMOTE_PORT']=$remoteAddress['port'];
}
if($this->localSocketUri!==null){
$localAddress=parse_url($this->localSocketUri);
if(isset($localAddress['host'],$localAddress['port'])){
$serverParams['SERVER_ADDR']=$localAddress['host'];
$serverParams['SERVER_PORT']=$localAddress['port'];
}
if(isset($localAddress['scheme'])&&$localAddress['scheme']==='https'){
$serverParams['HTTPS']='on';
}
}
$target=$request->getRequestTarget();
$request=new ServerRequest($request->getMethod(),$request->getUri(),$request->getHeaders(),$request->getBody(),$request->getProtocolVersion(),$serverParams
);
$request=$request->withRequestTarget($target);
$queryString=$request->getUri()->getQuery();
if($queryString!==''){
$queryParams=array();
parse_str($queryString,$queryParams);
$request=$request->withQueryParams($queryParams);
}
$cookies=ServerRequest::parseCookie($request->getHeaderLine('Cookie'));
if($cookies!==false){
$request=$request->withCookieParams($cookies);
}
if($originalTarget!==null){
$request=$request->withUri($request->getUri()->withPath(''),true
)->withRequestTarget($originalTarget);
}
$protocolVersion=$request->getProtocolVersion();
if($protocolVersion!=='1.1'&&$protocolVersion!=='1.0'){
throw new\InvalidArgumentException('Received request with invalid protocol version',505);
}
$requestTarget=$request->getRequestTarget();
if(strpos($requestTarget,'://')!==false&&substr($requestTarget,0,1)!=='/'){
$parts=parse_url($requestTarget);
if(!isset($parts['scheme'],$parts['host'])||$parts['scheme']!=='http'||isset($parts['fragment'])){
throw new\InvalidArgumentException('Invalid absolute-form request-target');
}
}
if($request->hasHeader('Host')){
$parts=parse_url('http://'.$request->getHeaderLine('Host'));
if(!$parts||!isset($parts['scheme'],$parts['host'])){
$parts=false;
}
unset($parts['scheme'],$parts['host'],$parts['port']);
if($parts===false||$parts){
throw new\InvalidArgumentException('Invalid Host header value');
}
}
if($request->getUri()->getHost()===''){
$parts=parse_url($this->localSocketUri);
if(!isset($parts['host'],$parts['port'])){
$parts=array('host'=>'127.0.0.1','port'=>80);
}
$request=$request->withUri($request->getUri()->withScheme('http')->withHost($parts['host'])->withPort($parts['port']),true
);
}
if($request->getUri()->getScheme()==='https'){
$request=$request->withUri($request->getUri()->withScheme('http')->withPort(443),true
);
}
$parts=parse_url($this->localSocketUri);
if(isset($parts['scheme'])&&$parts['scheme']==='https'){
$port=$request->getUri()->getPort();
if($port===null){
$port=parse_url('tcp://'.$request->getHeaderLine('Host'),PHP_URL_PORT);}
$request=$request->withUri($request->getUri()->withScheme('https')->withPort($port),true
);
}
$request=$request->withUri($request->getUri()->withUserInfo('u')->withUserInfo(''));
return$request;
}
}
namespace RingCentral\Psr7;
use Psr\Http\Message\StreamInterface;
abstract class MessageTrait
{
protected$headers=array();
protected$headerLines=array();
protected$protocol='1.1';
protected$stream;
function getProtocolVersion(){
return$this->protocol;
}
function withProtocolVersion($version){
if($this->protocol===$version){
return$this;
}
$new=clone$this;
$new->protocol=$version;
return$new;
}
function getHeaders(){
return$this->headerLines;
}
function hasHeader($header){
return isset($this->headers[strtolower($header)]);
}
function getHeader($header){
$name=strtolower($header);
return isset($this->headers[$name])?$this->headers[$name]:array();
}
function getHeaderLine($header){
return join(', ',$this->getHeader($header));
}
function withHeader($header,$value){
$new=clone$this;
$header=trim($header);
$name=strtolower($header);
if(!is_array($value)){
$new->headers[$name]=array(trim($value));
}else{
$new->headers[$name]=$value;
foreach($new->headers[$name]as&$v){
$v=trim($v);
}
}
foreach(array_keys($new->headerLines)as$key){
if(strtolower($key)===$name){
unset($new->headerLines[$key]);
}
}
$new->headerLines[$header]=$new->headers[$name];
return$new;
}
function withAddedHeader($header,$value){
if(!$this->hasHeader($header)){
return$this->withHeader($header,$value);
}
$header=trim($header);
$name=strtolower($header);
$value=(array)$value;
foreach($value as&$v){
$v=trim($v);
}
$new=clone$this;
$new->headers[$name]=array_merge($new->headers[$name],$value);
$new->headerLines[$header]=array_merge($new->headerLines[$header],$value);
return$new;
}
function withoutHeader($header){
if(!$this->hasHeader($header)){
return$this;
}
$new=clone$this;
$name=strtolower($header);
unset($new->headers[$name]);
foreach(array_keys($new->headerLines)as$key){
if(strtolower($key)===$name){
unset($new->headerLines[$key]);
}
}
return$new;
}
function getBody(){
if(!$this->stream){
$this->stream=stream_for('');
}
return$this->stream;
}
function withBody(StreamInterface$body){
if($body===$this->stream){
return$this;
}
$new=clone$this;
$new->stream=$body;
return$new;
}
protected function setHeaders(array$headers){
$this->headerLines=$this->headers=array();
foreach($headers as$header=>$value){
$header=trim($header);
$name=strtolower($header);
if(!is_array($value)){
$value=trim($value);
$this->headers[$name][]=$value;
$this->headerLines[$header][]=$value;
}else{
foreach($value as$v){
$v=trim($v);
$this->headers[$name][]=$v;
$this->headerLines[$header][]=$v;
}
}
}
}
}
namespace RingCentral\Psr7;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
class Request extends MessageTrait implements RequestInterface
{
private$method;
private$requestTarget;
private$uri;
function __construct($method,$uri,array$headers=array(),$body=null,$protocolVersion='1.1'){
if(is_string($uri)){
$uri=new Uri($uri);
}elseif(!($uri instanceof UriInterface)){
throw new\InvalidArgumentException('URI must be a string or Psr\Http\Message\UriInterface');
}
$this->method=strtoupper($method);
$this->uri=$uri;
$this->setHeaders($headers);
$this->protocol=$protocolVersion;
$host=$uri->getHost();
if($host&&!$this->hasHeader('Host')){
$this->updateHostFromUri($host);
}
if($body){
$this->stream=stream_for($body);
}
}
function getRequestTarget(){
if($this->requestTarget!==null){
return$this->requestTarget;
}
$target=$this->uri->getPath();
if($target==null){
$target='/';
}
if($this->uri->getQuery()){
$target.='?'.$this->uri->getQuery();
}
return$target;
}
function withRequestTarget($requestTarget){
if(preg_match('#\s#',$requestTarget)){
throw new InvalidArgumentException('Invalid request target provided; cannot contain whitespace');
}
$new=clone$this;
$new->requestTarget=$requestTarget;
return$new;
}
function getMethod(){
return$this->method;
}
function withMethod($method){
$new=clone$this;
$new->method=strtoupper($method);
return$new;
}
function getUri(){
return$this->uri;
}
function withUri(UriInterface$uri,$preserveHost=false){
if($uri===$this->uri){
return$this;
}
$new=clone$this;
$new->uri=$uri;
if(!$preserveHost){
if($host=$uri->getHost()){
$new->updateHostFromUri($host);
}
}
return$new;
}
function withHeader($header,$value){
$newInstance=parent::withHeader($header,$value);
return$newInstance;
}
private function updateHostFromUri($host){
if($port=$this->uri->getPort()){
$host.=':'.$port;
}
$this->headerLines=array('Host'=>array($host))+$this->headerLines;
$this->headers=array('host'=>array($host))+$this->headers;
}
}
namespace React\Http\Io;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use RingCentral\Psr7\Request;
class ServerRequest extends Request implements ServerRequestInterface
{
private$attributes=array();
private$serverParams;
private$fileParams=array();
private$cookies=array();
private$queryParams=array();
private$parsedBody;
function __construct($method,$uri,array$headers=array(),$body=null,$protocolVersion='1.1',$serverParams=array()){
$this->serverParams=$serverParams;
parent::__construct($method,$uri,$headers,$body,$protocolVersion);
}
function getServerParams(){
return$this->serverParams;
}
function getCookieParams(){
return$this->cookies;
}
function withCookieParams(array$cookies){
$new=clone$this;
$new->cookies=$cookies;
return$new;
}
function getQueryParams(){
return$this->queryParams;
}
function withQueryParams(array$query){
$new=clone$this;
$new->queryParams=$query;
return$new;
}
function getUploadedFiles(){
return$this->fileParams;
}
function withUploadedFiles(array$uploadedFiles){
$new=clone$this;
$new->fileParams=$uploadedFiles;
return$new;
}
function getParsedBody(){
return$this->parsedBody;
}
function withParsedBody($data){
$new=clone$this;
$new->parsedBody=$data;
return$new;
}
function getAttributes(){
return$this->attributes;
}
function getAttribute($name,$default=null){
if(!key_exists($name,$this->attributes)){
return$default;
}
return$this->attributes[$name];
}
function withAttribute($name,$value){
$new=clone$this;
$new->attributes[$name]=$value;
return$new;
}
function withoutAttribute($name){
$new=clone$this;
unset($new->attributes[$name]);
return$new;
}
static function parseCookie($cookie){
if(strpos($cookie,',')!==false){
return false;
}
$cookieArray=explode(';',$cookie);
$result=array();
foreach($cookieArray as$pair){
$pair=trim($pair);
$nameValuePair=explode('=',$pair,2);
if(count($nameValuePair)===2){
$key=urldecode($nameValuePair[0]);
$value=urldecode($nameValuePair[1]);
$result[$key]=$value;
}
}
return$result;
}
}
namespace React\Http\Io;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use InvalidArgumentException;
use RuntimeException;
final class UploadedFile implements UploadedFileInterface
{
private$stream;
private$size;
private$error;
private$filename;
private$mediaType;
function __construct(StreamInterface$stream,$size,$error,$filename,$mediaType){
$this->stream=$stream;
$this->size=$size;
if(!is_int($error)||!in_array($error,array(UPLOAD_ERR_OK,UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE,UPLOAD_ERR_PARTIAL,UPLOAD_ERR_NO_FILE,UPLOAD_ERR_NO_TMP_DIR,UPLOAD_ERR_CANT_WRITE,UPLOAD_ERR_EXTENSION,))){
throw new InvalidArgumentException('Invalid error code, must be an UPLOAD_ERR_* constant');
}
$this->error=$error;
$this->filename=$filename;
$this->mediaType=$mediaType;
}
function getStream(){
if($this->error!==UPLOAD_ERR_OK){
throw new RuntimeException('Cannot retrieve stream due to upload error');
}
return$this->stream;
}
function moveTo($targetPath){
throw new RuntimeException('Not implemented');
}
function getSize(){
return$this->size;
}
function getError(){
return$this->error;
}
function getClientFilename(){
return$this->filename;
}
function getClientMediaType(){
return$this->mediaType;
}
}
namespace React\Http\Middleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Io\HttpBodyStream;
use React\Http\Io\PauseBufferStream;
use React\Promise;
use React\Promise\PromiseInterface;
use React\Promise\Deferred;
use React\Stream\ReadableStreamInterface;
final class LimitConcurrentRequestsMiddleware
{
private$limit;
private$pending=0;
private$queue=array();
function __construct($limit){
$this->limit=$limit;
}
function __invoke(ServerRequestInterface$request,$next){
if($this->pending<$this->limit){
++$this->pending;
try{
$response=$next($request);
}catch(\Exception$e){
$this->processQueue();
throw$e;
}catch(\Throwable$e){$this->processQueue();
throw$e;}
if($response instanceof ResponseInterface){
$this->processQueue();
return$response;
}
return$this->await(Promise\resolve($response));
}
$body=$request->getBody();
if($body instanceof ReadableStreamInterface){
$size=$body->getSize();
$body=new PauseBufferStream($body);
$body->pauseImplicit();
$request=$request->withBody(new HttpBodyStream($body,$size
));
}
$queue=&$this->queue;
$queue[]=null;
end($queue);
$id=key($queue);
$deferred=new Deferred(function($_,$reject)use(&$queue,$id){
unset($queue[$id]);
$reject(new\RuntimeException('Cancelled queued next handler'));
});
$queue[$id]=$deferred;
$pending=&$this->pending;
$that=$this;
return$deferred->promise()->then(function()use($request,$next,$body,&$pending,$that){
++$pending;
try{
$response=$next($request);
}catch(\Exception$e){
$that->processQueue();
throw$e;
}catch(\Throwable$e){$that->processQueue();
throw$e;}
if($body instanceof PauseBufferStream){
$body->resumeImplicit();
}
return$that->await(Promise\resolve($response));
});
}
function await(PromiseInterface$promise){
$that=$this;
return$promise->then(function($response)use($that){
$that->processQueue();
return$response;
},function($error)use($that){
$that->processQueue();
return Promise\reject($error);
});
}
function processQueue(){
if(--$this->pending>=$this->limit||!$this->queue){
return;
}
$first=reset($this->queue);
unset($this->queue[key($this->queue)]);
$first->resolve();
}
}
namespace React\Http\Middleware;
use OverflowException;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Io\IniUtil;
use React\Promise\Stream;
use React\Stream\ReadableStreamInterface;
use RingCentral\Psr7\BufferStream;
final class RequestBodyBufferMiddleware
{
private$sizeLimit;
function __construct($sizeLimit=null){
if($sizeLimit===null){
$sizeLimit=ini_get('post_max_size');
}
$this->sizeLimit=IniUtil::iniSizeToBytes($sizeLimit);
}
function __invoke(ServerRequestInterface$request,$stack){
$body=$request->getBody();
$size=$body->getSize();
if($size===0||!$body instanceof ReadableStreamInterface){
if($body instanceof ReadableStreamInterface||$size>$this->sizeLimit){
$request=$request->withBody(new BufferStream(0));
}
return$stack($request);
}
$sizeLimit=$this->sizeLimit;
if($size>$this->sizeLimit){
$sizeLimit=0;
}
return Stream\buffer($body,$sizeLimit)->then(function($buffer)use($request,$stack){
$stream=new BufferStream(strlen($buffer));
$stream->write($buffer);
$request=$request->withBody($stream);
return$stack($request);
},function($error)use($stack,$request,$body){
if($error instanceof OverflowException){
return Stream\first($body,'close')->then(function()use($stack,$request){
return$stack($request);
});
}
throw$error;
});
}
}
namespace React\Http\Middleware;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Io\MultipartParser;
final class RequestBodyParserMiddleware
{
private$multipart;
function __construct($uploadMaxFilesize=null,$maxFileUploads=null){
$this->multipart=new MultipartParser($uploadMaxFilesize,$maxFileUploads);
}
function __invoke(ServerRequestInterface$request,$next){
$type=strtolower($request->getHeaderLine('Content-Type'));
list($type)=explode(';',$type);
if($type==='application/x-www-form-urlencoded'){
return$next($this->parseFormUrlencoded($request));
}
if($type==='multipart/form-data'){
return$next($this->multipart->parse($request));
}
return$next($request);
}
private function parseFormUrlencoded(ServerRequestInterface$request){
$ret=array();
@parse_str((string)$request->getBody(),$ret);
return$request->withParsedBody($ret);
}
}
namespace RingCentral\Psr7;
use Psr\Http\Message\ResponseInterface;
class Response extends MessageTrait implements ResponseInterface
{
private static$phrases=array(100=>'Continue',101=>'Switching Protocols',102=>'Processing',200=>'OK',201=>'Created',202=>'Accepted',203=>'Non-Authoritative Information',204=>'No Content',205=>'Reset Content',206=>'Partial Content',207=>'Multi-status',208=>'Already Reported',300=>'Multiple Choices',301=>'Moved Permanently',302=>'Found',303=>'See Other',304=>'Not Modified',305=>'Use Proxy',306=>'Switch Proxy',307=>'Temporary Redirect',400=>'Bad Request',401=>'Unauthorized',402=>'Payment Required',403=>'Forbidden',404=>'Not Found',405=>'Method Not Allowed',406=>'Not Acceptable',407=>'Proxy Authentication Required',408=>'Request Time-out',409=>'Conflict',410=>'Gone',411=>'Length Required',412=>'Precondition Failed',413=>'Request Entity Too Large',414=>'Request-URI Too Large',415=>'Unsupported Media Type',416=>'Requested range not satisfiable',417=>'Expectation Failed',418=>'I\'m a teapot',422=>'Unprocessable Entity',423=>'Locked',424=>'Failed Dependency',425=>'Unordered Collection',426=>'Upgrade Required',428=>'Precondition Required',429=>'Too Many Requests',431=>'Request Header Fields Too Large',500=>'Internal Server Error',501=>'Not Implemented',502=>'Bad Gateway',503=>'Service Unavailable',504=>'Gateway Time-out',505=>'HTTP Version not supported',506=>'Variant Also Negotiates',507=>'Insufficient Storage',508=>'Loop Detected',511=>'Network Authentication Required',);
private$reasonPhrase='';
private$statusCode=200;
function __construct($status=200,array$headers=array(),$body=null,$version='1.1',$reason=null
){
$this->statusCode=(int)$status;
if($body!==null){
$this->stream=stream_for($body);
}
$this->setHeaders($headers);
if(!$reason&&isset(self::$phrases[$this->statusCode])){
$this->reasonPhrase=self::$phrases[$status];
}else{
$this->reasonPhrase=(string)$reason;
}
$this->protocol=$version;
}
function getStatusCode(){
return$this->statusCode;
}
function getReasonPhrase(){
return$this->reasonPhrase;
}
function withStatus($code,$reasonPhrase=''){
$new=clone$this;
$new->statusCode=(int)$code;
if(!$reasonPhrase&&isset(self::$phrases[$new->statusCode])){
$reasonPhrase=self::$phrases[$new->statusCode];
}
$new->reasonPhrase=$reasonPhrase;
return$new;
}
}
namespace React\Http;
use React\Http\Io\HttpBodyStream;
use React\Stream\ReadableStreamInterface;
use RingCentral\Psr7\Response as Psr7Response;
class Response extends Psr7Response
{
function __construct($status=200,array$headers=array(),$body=null,$version='1.1',$reason=null
){
if($body instanceof ReadableStreamInterface){
$body=new HttpBodyStream($body,null);
}
parent::__construct($status,$headers,$body,$version,$reason
);
}
}
namespace React\Http;
use Evenement\EventEmitter;
use React\Http\Io\IniUtil;
use React\Http\Middleware\LimitConcurrentRequestsMiddleware;
use React\Http\Middleware\RequestBodyBufferMiddleware;
use React\Http\Middleware\RequestBodyParserMiddleware;
use React\Socket\ServerInterface;
final class Server extends EventEmitter
{
const MAXIMUM_CONCURRENT_REQUESTS=100;
private$streamingServer;
function __construct($requestHandler){
if(!is_callable($requestHandler)&&!is_array($requestHandler)){
throw new\InvalidArgumentException('Invalid request handler given');
}
$middleware=array();
$middleware[]=new LimitConcurrentRequestsMiddleware($this->getConcurrentRequestsLimit());
$middleware[]=new RequestBodyBufferMiddleware;
$enablePostDataReading=ini_get('enable_post_data_reading');
if($enablePostDataReading!==''){
$middleware[]=new RequestBodyParserMiddleware;
}
if(is_callable($requestHandler)){
$middleware[]=$requestHandler;
}else{
$middleware=array_merge($middleware,$requestHandler);
}
$this->streamingServer=new StreamingServer($middleware);
$that=$this;
$this->streamingServer->on('error',function($error)use($that){
$that->emit('error',array($error));
});
}
function listen(ServerInterface$server){
$this->streamingServer->listen($server);
}
private function getConcurrentRequestsLimit(){
if(ini_get('memory_limit')==-1){
return self::MAXIMUM_CONCURRENT_REQUESTS;
}
$availableMemory=IniUtil::iniSizeToBytes(ini_get('memory_limit'))/4;
$concurrentRequests=ceil($availableMemory/IniUtil::iniSizeToBytes(ini_get('post_max_size')));
if($concurrentRequests>=self::MAXIMUM_CONCURRENT_REQUESTS){
return self::MAXIMUM_CONCURRENT_REQUESTS;
}
return$concurrentRequests;
}
}
namespace React\Http;
use Evenement\EventEmitter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Io\ChunkedDecoder;
use React\Http\Io\ChunkedEncoder;
use React\Http\Io\CloseProtectionStream;
use React\Http\Io\HttpBodyStream;
use React\Http\Io\LengthLimitedStream;
use React\Http\Io\MiddlewareRunner;
use React\Http\Io\RequestHeaderParser;
use React\Http\Io\ServerRequest;
use React\Promise;
use React\Promise\CancellablePromiseInterface;
use React\Promise\PromiseInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
final class StreamingServer extends EventEmitter
{
private$callback;
function __construct($requestHandler){
if(!is_callable($requestHandler)&&!is_array($requestHandler)){
throw new\InvalidArgumentException('Invalid request handler given');
}elseif(!is_callable($requestHandler)){
$requestHandler=new MiddlewareRunner($requestHandler);
}
$this->callback=$requestHandler;
}
function listen(ServerInterface$socket){
$socket->on('connection',array($this,'handleConnection'));
}
function handleConnection(ConnectionInterface$conn){
$uriLocal=$conn->getLocalAddress();
if($uriLocal!==null){
$uriLocal=strtr($uriLocal,array('tcp://'=>'http://','tls://'=>'https://'));
}
$uriRemote=$conn->getRemoteAddress();
$that=$this;
$parser=new RequestHeaderParser($uriLocal,$uriRemote);
$listener=array($parser,'feed');
$parser->on('headers',function(ServerRequestInterface$request,$bodyBuffer)use($conn,$listener,$that){
$conn->removeListener('data',$listener);
$that->handleRequest($conn,$request);
if($bodyBuffer!==''){
$conn->emit('data',array($bodyBuffer));
}
});
$conn->on('data',$listener);
$parser->on('error',function(\Exception$e)use($conn,$listener,$that){
$conn->removeListener('data',$listener);
$that->emit('error',array($e));
$that->writeError($conn,$e->getCode()!==0?$e->getCode():400
);
});
}
function handleRequest(ConnectionInterface$conn,ServerRequestInterface$request){
$contentLength=0;
$stream=new CloseProtectionStream($conn);
if($request->hasHeader('Transfer-Encoding')){
if(strtolower($request->getHeaderLine('Transfer-Encoding'))!=='chunked'){
$this->emit('error',array(new\InvalidArgumentException('Only chunked-encoding is allowed for Transfer-Encoding')));
return$this->writeError($conn,501,$request);
}
if($request->hasHeader('Content-Length')){
$this->emit('error',array(new\InvalidArgumentException('Using both `Transfer-Encoding: chunked` and `Content-Length` is not allowed')));
return$this->writeError($conn,400,$request);
}
$stream=new ChunkedDecoder($stream);
$contentLength=null;
}elseif($request->hasHeader('Content-Length')){
$string=$request->getHeaderLine('Content-Length');
$contentLength=(int)$string;
if((string)$contentLength!==$string){
$this->emit('error',array(new\InvalidArgumentException('The value of `Content-Length` is not valid')));
return$this->writeError($conn,400,$request);
}
$stream=new LengthLimitedStream($stream,$contentLength);
}
$request=$request->withBody(new HttpBodyStream($stream,$contentLength));
if($request->getProtocolVersion()!=='1.0'&&'100-continue'===strtolower($request->getHeaderLine('Expect'))){
$conn->write("HTTP/1.1 100 Continue\r\n\r\n");
}
$callback=$this->callback;
try{
$response=$callback($request);
}catch(\Exception$error){
$response=Promise\reject($error);
}catch(\Throwable$error){$response=Promise\reject($error);}
if($response instanceof CancellablePromiseInterface){
$conn->on('close',function()use($response){
$response->cancel();
});
}
if($contentLength===0){
$stream->emit('end');
$stream->close();
}
if($response instanceof ResponseInterface){
return$this->handleResponse($conn,$request,$response);
}
if(!$response instanceof PromiseInterface){
$response=Promise\resolve($response);
}
$that=$this;
$response->then(function($response)use($that,$conn,$request){
if(!$response instanceof ResponseInterface){
$message='The response callback is expected to resolve with an object implementing Psr\Http\Message\ResponseInterface, but resolved with "%s" instead.';
$message=sprintf($message,is_object($response)?get_class($response):gettype($response));
$exception=new\RuntimeException($message);
$that->emit('error',array($exception));
return$that->writeError($conn,500,$request);
}
$that->handleResponse($conn,$request,$response);
},function($error)use($that,$conn,$request){
$message='The response callback is expected to resolve with an object implementing Psr\Http\Message\ResponseInterface, but rejected with "%s" instead.';
$message=sprintf($message,is_object($error)?get_class($error):gettype($error));
$previous=null;
if($error instanceof\Throwable||$error instanceof\Exception){
$previous=$error;
}
$exception=new\RuntimeException($message,null,$previous);
$that->emit('error',array($exception));
return$that->writeError($conn,500,$request);
}
);
}
function writeError(ConnectionInterface$conn,$code,ServerRequestInterface$request=null){
$response=new Response($code,array('Content-Type'=>'text/plain'),'Error '.$code
);
$reason=$response->getReasonPhrase();
if($reason!==''){
$body=$response->getBody();
$body->seek(0,SEEK_END);
$body->write(': '.$reason);
}
if($request===null){
$request=new ServerRequest('GET','/',array(),null,'1.1');
}
$this->handleResponse($conn,$request,$response);
}
function handleResponse(ConnectionInterface$connection,ServerRequestInterface$request,ResponseInterface$response){
$body=$response->getBody();
if(!$connection->isWritable()){
$body->close();
return;
}
$response=$response->withProtocolVersion($request->getProtocolVersion());
if(!$response->hasHeader('X-Powered-By')){
$response=$response->withHeader('X-Powered-By','React/alpha');
}
if($response->hasHeader('X-Powered-By')&&$response->getHeaderLine('X-Powered-By')===''){
$response=$response->withoutHeader('X-Powered-By');
}
$response=$response->withoutHeader('Transfer-Encoding');
if(!$response->hasHeader('Date')){
$response=$response->withHeader('Date',gmdate('D, d M Y H:i:s').' GMT');
}
if($response->hasHeader('Date')&&$response->getHeaderLine('Date')===''){
$response=$response->withoutHeader('Date');
}
if(!$body instanceof HttpBodyStream){
$response=$response->withHeader('Content-Length',(string)$body->getSize());
}elseif(!$response->hasHeader('Content-Length')&&$request->getProtocolVersion()==='1.1'){
$response=$response->withHeader('Transfer-Encoding','chunked');
}
if($request->getProtocolVersion()==='1.1'){
$response=$response->withHeader('Connection','close');
}
$code=$response->getStatusCode();
if(($request->getMethod()==='CONNECT'&&$code>=200&&$code<300)||($code>=100&&$code<200)||$code===204){
$response=$response->withoutHeader('Content-Length')->withoutHeader('Transfer-Encoding');
}
if($code===101){
$response=$response->withHeader('Connection','upgrade');
}
if(($code===101||($request->getMethod()==='CONNECT'&&$code>=200&&$code<300))&&$body instanceof HttpBodyStream&&$body->input instanceof WritableStreamInterface){
if($request->getBody()->isReadable()){
$request->getBody()->on('close',function()use($connection,$body){
if($body->input->isWritable()){
$connection->pipe($body->input);
$connection->resume();
}
});
}elseif($body->input->isWritable()){
$connection->pipe($body->input);
$connection->resume();
}
}
$headers="HTTP/".$response->getProtocolVersion()." ".$response->getStatusCode()." ".$response->getReasonPhrase()."\r\n";
foreach($response->getHeaders()as$name=>$values){
foreach($values as$value){
$headers.=$name.": ".$value."\r\n";
}
}
if($request->getMethod()==='HEAD'||$code===100||($code>101&&$code<200)||$code===204||$code===304){
$body='';
}
if(!$body instanceof ReadableStreamInterface||!$body->isReadable()){
if($body instanceof ReadableStreamInterface&&$response->getHeaderLine('Transfer-Encoding')==='chunked'){
$body="0\r\n\r\n";
}
$connection->write($headers."\r\n".$body);
$connection->end();
return;
}
$connection->write($headers."\r\n");
if($response->getHeaderLine('Transfer-Encoding')==='chunked'){
$body=new ChunkedEncoder($body);
}
$connection->on('close',array($body,'close'));
$body->pipe($connection);
}
}
namespace React\Promise;
interface PromiseInterface
{
function then(callable$onFulfilled=null,callable$onRejected=null,callable$onProgress=null);
}
namespace React\Promise;
interface CancellablePromiseInterface extends PromiseInterface
{
function cancel();
}
namespace React\Promise;
class CancellationQueue
{
private$started=false;
private$queue=[];
function __invoke(){
if($this->started){
return;
}
$this->started=true;
$this->drain();
}
function enqueue($cancellable){
if(!method_exists($cancellable,'then')||!method_exists($cancellable,'cancel')){
return;
}
$length=array_push($this->queue,$cancellable);
if($this->started&&1===$length){
$this->drain();
}
}
private function drain(){
for($i=key($this->queue);isset($this->queue[$i]);$i++){
$cancellable=$this->queue[$i];
$exception=null;
try{
$cancellable->cancel();
}catch(\Throwable$exception){
}catch(\Exception$exception){
}
unset($this->queue[$i]);
if($exception){
throw$exception;
}
}
$this->queue=[];
}
}
namespace React\Promise;
interface PromisorInterface
{
function promise();
}
namespace React\Promise;
class Deferred implements PromisorInterface
{
private$promise;
private$resolveCallback;
private$rejectCallback;
private$notifyCallback;
private$canceller;
function __construct(callable$canceller=null){
$this->canceller=$canceller;
}
function promise(){
if(null===$this->promise){
$this->promise=new Promise(function($resolve,$reject,$notify){
$this->resolveCallback=$resolve;
$this->rejectCallback=$reject;
$this->notifyCallback=$notify;
},$this->canceller);
$this->canceller=null;
}
return$this->promise;
}
function resolve($value=null){
$this->promise();
call_user_func($this->resolveCallback,$value);
}
function reject($reason=null){
$this->promise();
call_user_func($this->rejectCallback,$reason);
}
function notify($update=null){
$this->promise();
call_user_func($this->notifyCallback,$update);
}
function progress($update=null){
$this->notify($update);
}
}
namespace React\Promise\Exception;
class LengthException extends\LengthException
{
}
namespace React\Promise;
interface ExtendedPromiseInterface extends PromiseInterface
{
function done(callable$onFulfilled=null,callable$onRejected=null,callable$onProgress=null);
function otherwise(callable$onRejected);
function always(callable$onFulfilledOrRejected);
function progress(callable$onProgress);
}
namespace React\Promise;
class FulfilledPromise implements ExtendedPromiseInterface,CancellablePromiseInterface
{
private$value;
function __construct($value=null){
if($value instanceof PromiseInterface){
throw new\InvalidArgumentException('You cannot create React\Promise\FulfilledPromise with a promise. Use React\Promise\resolve($promiseOrValue) instead.');
}
$this->value=$value;
}
function then(callable$onFulfilled=null,callable$onRejected=null,callable$onProgress=null){
if(null===$onFulfilled){
return$this;
}
try{
return resolve($onFulfilled($this->value));
}catch(\Throwable$exception){
return new RejectedPromise($exception);
}catch(\Exception$exception){
return new RejectedPromise($exception);
}
}
function done(callable$onFulfilled=null,callable$onRejected=null,callable$onProgress=null){
if(null===$onFulfilled){
return;
}
$result=$onFulfilled($this->value);
if($result instanceof ExtendedPromiseInterface){
$result->done();
}
}
function otherwise(callable$onRejected){
return$this;
}
function always(callable$onFulfilledOrRejected){
return$this->then(function($value)use($onFulfilledOrRejected){
return resolve($onFulfilledOrRejected())->then(function()use($value){
return$value;
});
});
}
function progress(callable$onProgress){
return$this;
}
function cancel(){
}
}
namespace React\Promise;
class LazyPromise implements ExtendedPromiseInterface,CancellablePromiseInterface
{
private$factory;
private$promise;
function __construct(callable$factory){
$this->factory=$factory;
}
function then(callable$onFulfilled=null,callable$onRejected=null,callable$onProgress=null){
return$this->promise()->then($onFulfilled,$onRejected,$onProgress);
}
function done(callable$onFulfilled=null,callable$onRejected=null,callable$onProgress=null){
return$this->promise()->done($onFulfilled,$onRejected,$onProgress);
}
function otherwise(callable$onRejected){
return$this->promise()->otherwise($onRejected);
}
function always(callable$onFulfilledOrRejected){
return$this->promise()->always($onFulfilledOrRejected);
}
function progress(callable$onProgress){
return$this->promise()->progress($onProgress);
}
function cancel(){
return$this->promise()->cancel();
}
function promise(){
if(null===$this->promise){
try{
$this->promise=resolve(call_user_func($this->factory));
}catch(\Throwable$exception){
$this->promise=new RejectedPromise($exception);
}catch(\Exception$exception){
$this->promise=new RejectedPromise($exception);
}
}
return$this->promise;
}
}
namespace React\Promise;
class Promise implements ExtendedPromiseInterface,CancellablePromiseInterface
{
private$canceller;
private$result;
private$handlers=[];
private$progressHandlers=[];
private$requiredCancelRequests=0;
private$cancelRequests=0;
function __construct(callable$resolver,callable$canceller=null){
$this->canceller=$canceller;
$cb=$resolver;
$resolver=$canceller=null;
$this->call($cb);
}
function then(callable$onFulfilled=null,callable$onRejected=null,callable$onProgress=null){
if(null!==$this->result){
return$this->result->then($onFulfilled,$onRejected,$onProgress);
}
if(null===$this->canceller){
return new static($this->resolver($onFulfilled,$onRejected,$onProgress));
}
$parent=$this;
++$parent->requiredCancelRequests;
return new static($this->resolver($onFulfilled,$onRejected,$onProgress),static function()use(&$parent){
if(++$parent->cancelRequests>=$parent->requiredCancelRequests){
$parent->cancel();
}
$parent=null;
}
);
}
function done(callable$onFulfilled=null,callable$onRejected=null,callable$onProgress=null){
if(null!==$this->result){
return$this->result->done($onFulfilled,$onRejected,$onProgress);
}
$this->handlers[]=static function(ExtendedPromiseInterface$promise)use($onFulfilled,$onRejected){
$promise
->done($onFulfilled,$onRejected);
};
if($onProgress){
$this->progressHandlers[]=$onProgress;
}
}
function otherwise(callable$onRejected){
return$this->then(null,static function($reason)use($onRejected){
if(!_checkTypehint($onRejected,$reason)){
return new RejectedPromise($reason);
}
return$onRejected($reason);
});
}
function always(callable$onFulfilledOrRejected){
return$this->then(static function($value)use($onFulfilledOrRejected){
return resolve($onFulfilledOrRejected())->then(function()use($value){
return$value;
});
},static function($reason)use($onFulfilledOrRejected){
return resolve($onFulfilledOrRejected())->then(function()use($reason){
return new RejectedPromise($reason);
});
});
}
function progress(callable$onProgress){
return$this->then(null,null,$onProgress);
}
function cancel(){
if(null===$this->canceller||null!==$this->result){
return;
}
$canceller=$this->canceller;
$this->canceller=null;
$this->call($canceller);
}
private function resolver(callable$onFulfilled=null,callable$onRejected=null,callable$onProgress=null){
return function($resolve,$reject,$notify)use($onFulfilled,$onRejected,$onProgress){
if($onProgress){
$progressHandler=static function($update)use($notify,$onProgress){
try{
$notify($onProgress($update));
}catch(\Throwable$e){
$notify($e);
}catch(\Exception$e){
$notify($e);
}
};
}else{
$progressHandler=$notify;
}
$this->handlers[]=static function(ExtendedPromiseInterface$promise)use($onFulfilled,$onRejected,$resolve,$reject,$progressHandler){
$promise
->then($onFulfilled,$onRejected)->done($resolve,$reject,$progressHandler);
};
$this->progressHandlers[]=$progressHandler;
};
}
private function reject($reason=null){
if(null!==$this->result){
return;
}
$this->settle(reject($reason));
}
private function settle(ExtendedPromiseInterface$promise){
$promise=$this->unwrap($promise);
if($promise===$this){
$promise=new RejectedPromise(new\LogicException('Cannot resolve a promise with itself.'));
}
$handlers=$this->handlers;
$this->progressHandlers=$this->handlers=[];
$this->result=$promise;
$this->canceller=null;
foreach($handlers as$handler){
$handler($promise);
}
}
private function unwrap($promise){
$promise=$this->extract($promise);
while($promise instanceof self&&null!==$promise->result){
$promise=$this->extract($promise->result);
}
return$promise;
}
private function extract($promise){
if($promise instanceof LazyPromise){
$promise=$promise->promise();
}
return$promise;
}
private function call(callable$cb){
$callback=$cb;
$cb=null;
if(is_array($callback)){
$ref=new\ReflectionMethod($callback[0],$callback[1]);
}elseif(is_object($callback)&&!$callback instanceof\Closure){
$ref=new\ReflectionMethod($callback,'__invoke');
}else{
$ref=new\ReflectionFunction($callback);
}
$args=$ref->getNumberOfParameters();
try{
if($args===0){
$callback();
}else{
$target=&$this;
$progressHandlers=&$this->progressHandlers;
$callback(static function($value=null)use(&$target){
if($target!==null){
$target->settle(resolve($value));
$target=null;
}
},static function($reason=null)use(&$target){
if($target!==null){
$target->reject($reason);
$target=null;
}
},static function($update=null)use(&$progressHandlers){
foreach($progressHandlers as$handler){
$handler($update);
}
}
);
}
}catch(\Throwable$e){
$target=null;
$this->reject($e);
}catch(\Exception$e){
$target=null;
$this->reject($e);
}
}
}
namespace React\Promise;
class RejectedPromise implements ExtendedPromiseInterface,CancellablePromiseInterface
{
private$reason;
function __construct($reason=null){
if($reason instanceof PromiseInterface){
throw new\InvalidArgumentException('You cannot create React\Promise\RejectedPromise with a promise. Use React\Promise\reject($promiseOrValue) instead.');
}
$this->reason=$reason;
}
function then(callable$onFulfilled=null,callable$onRejected=null,callable$onProgress=null){
if(null===$onRejected){
return$this;
}
try{
return resolve($onRejected($this->reason));
}catch(\Throwable$exception){
return new RejectedPromise($exception);
}catch(\Exception$exception){
return new RejectedPromise($exception);
}
}
function done(callable$onFulfilled=null,callable$onRejected=null,callable$onProgress=null){
if(null===$onRejected){
throw UnhandledRejectionException::resolve($this->reason);
}
$result=$onRejected($this->reason);
if($result instanceof self){
throw UnhandledRejectionException::resolve($result->reason);
}
if($result instanceof ExtendedPromiseInterface){
$result->done();
}
}
function otherwise(callable$onRejected){
if(!_checkTypehint($onRejected,$this->reason)){
return$this;
}
return$this->then(null,$onRejected);
}
function always(callable$onFulfilledOrRejected){
return$this->then(null,function($reason)use($onFulfilledOrRejected){
return resolve($onFulfilledOrRejected())->then(function()use($reason){
return new RejectedPromise($reason);
});
});
}
function progress(callable$onProgress){
return$this;
}
function cancel(){
}
}
namespace React\Promise\Stream;
use Evenement\EventEmitter;
use InvalidArgumentException;
use React\Promise\CancellablePromiseInterface;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;
class UnwrapReadableStream extends EventEmitter implements ReadableStreamInterface
{
private$promise;
private$closed=false;
function __construct(PromiseInterface$promise){
$out=$this;
$closed=&$this->closed;
$this->promise=$promise->then(function($stream){
if(!($stream instanceof ReadableStreamInterface)){
throw new InvalidArgumentException('Not a readable stream');
}
return$stream;
}
)->then(function(ReadableStreamInterface$stream)use($out,&$closed){
if(!$stream->isReadable()){
$out->close();
return$stream;
}
if($closed){
$stream->close();
return$stream;
}
$stream->on('data',function($data)use($out){
$out->emit('data',array($data,$out));
});
$stream->on('end',function()use($out,&$closed){
if(!$closed){
$out->emit('end',array($out));
$out->close();
}
});
$stream->on('error',function($error)use($out){
$out->emit('error',array($error,$out));
$out->close();
});
$stream->on('close',array($out,'close'));
$out->on('close',array($stream,'close'));
return$stream;
},function($e)use($out,&$closed){
if(!$closed){
$out->emit('error',array($e,$out));
$out->close();
}
}
);
}
function isReadable(){
return!$this->closed;
}
function pause(){
$this->promise->then(function(ReadableStreamInterface$stream){
$stream->pause();
});
}
function resume(){
$this->promise->then(function(ReadableStreamInterface$stream){
$stream->resume();
});
}
function pipe(WritableStreamInterface$dest,array$options=array()){
Util::pipe($this,$dest,$options);
return$dest;
}
function close(){
if($this->closed){
return;
}
$this->closed=true;
if($this->promise instanceof CancellablePromiseInterface){
$this->promise->cancel();
}
$this->emit('close',array($this));
}
}
namespace React\Promise\Stream;
use Evenement\EventEmitter;
use InvalidArgumentException;
use React\Promise\CancellablePromiseInterface;
use React\Promise\PromiseInterface;
use React\Stream\WritableStreamInterface;
class UnwrapWritableStream extends EventEmitter implements WritableStreamInterface
{
private$promise;
private$stream;
private$buffer='';
private$closed=false;
private$ending=false;
function __construct(PromiseInterface$promise){
$out=$this;
$store=&$this->stream;
$buffer=&$this->buffer;
$ending=&$this->ending;
$closed=&$this->closed;
$this->promise=$promise->then(function($stream){
if(!($stream instanceof WritableStreamInterface)){
throw new InvalidArgumentException('Not a writable stream');
}
return$stream;
}
)->then(function(WritableStreamInterface$stream)use($out,&$store,&$buffer,&$ending,&$closed){
if(!$stream->isWritable()){
$out->close();
return$stream;
}
if($closed){
$stream->close();
return$stream;
}
$stream->on('drain',function()use($out){
$out->emit('drain',array($out));
});
$stream->on('error',function($error)use($out){
$out->emit('error',array($error,$out));
$out->close();
});
$stream->on('close',array($out,'close'));
$out->on('close',array($stream,'close'));
if($buffer!==''){
$drained=$stream->write($buffer)!==false;
$buffer='';
if($drained){
$out->emit('drain',array($out));
}
}
if($ending){
$stream->end();
}else{
$store=$stream;
}
return$stream;
},function($e)use($out,&$closed){
if(!$closed){
$out->emit('error',array($e,$out));
$out->close();
}
}
);
}
function write($data){
if($this->ending){
return;
}
if($this->stream!==null){
return$this->stream->write($data);
}
$this->buffer.=$data;
return false;
}
function end($data=null){
if($this->ending){
return;
}
$this->ending=true;
if($this->stream!==null){
return$this->stream->end($data);
}
if($data!==null){
$this->buffer.=$data;
}
}
function isWritable(){
return!$this->ending;
}
function close(){
if($this->closed){
return;
}
$this->buffer='';
$this->ending=true;
$this->closed=true;
if($this->promise instanceof CancellablePromiseInterface){
$this->promise->cancel();
}
$this->emit('close',array($this));
}
}
namespace React\Promise\Timer;
use RuntimeException;
class TimeoutException extends RuntimeException
{
private$timeout;
function __construct($timeout,$message=null,$code=null,$previous=null){
parent::__construct($message,$code,$previous);
$this->timeout=$timeout;
}
function getTimeout(){
return$this->timeout;
}
}
namespace React\Promise;
class UnhandledRejectionException extends\RuntimeException
{
private$reason;
static function resolve($reason){
if($reason instanceof\Exception||$reason instanceof\Throwable){
return$reason;
}
return new static($reason);
}
function __construct($reason){
$this->reason=$reason;
$message=sprintf('Unhandled Rejection: %s',json_encode($reason));
parent::__construct($message,0);
}
function getReason(){
return$this->reason;
}
}
namespace React\Stream;
interface DuplexStreamInterface extends ReadableStreamInterface,WritableStreamInterface
{
}
namespace React\Socket;
use React\Stream\DuplexStreamInterface;
interface ConnectionInterface extends DuplexStreamInterface
{
function getRemoteAddress();
function getLocalAddress();
}
namespace React\Socket;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Stream\DuplexResourceStream;
use React\Stream\Util;
use React\Stream\WritableResourceStream;
use React\Stream\WritableStreamInterface;
class Connection extends EventEmitter implements ConnectionInterface
{
var$unix=false;
var$encryptionEnabled=false;
var$stream;
private$input;
function __construct($resource,LoopInterface$loop){
$clearCompleteBuffer=PHP_VERSION_ID<50608;
$limitWriteChunks=(PHP_VERSION_ID<70018||(PHP_VERSION_ID>=70100&&PHP_VERSION_ID<70104));
$this->input=new DuplexResourceStream($resource,$loop,$clearCompleteBuffer?-1:null,new WritableResourceStream($resource,$loop,null,$limitWriteChunks?8192:null));
$this->stream=$resource;
Util::forwardEvents($this->input,$this,array('data','end','error','close','pipe','drain'));
$this->input->on('close',array($this,'close'));
}
function isReadable(){
return$this->input->isReadable();
}
function isWritable(){
return$this->input->isWritable();
}
function pause(){
$this->input->pause();
}
function resume(){
$this->input->resume();
}
function pipe(WritableStreamInterface$dest,array$options=array()){
return$this->input->pipe($dest,$options);
}
function write($data){
return$this->input->write($data);
}
function end($data=null){
$this->input->end($data);
}
function close(){
$this->input->close();
$this->handleClose();
$this->removeAllListeners();
}
function handleClose(){
if(!is_resource($this->stream)){
return;
}
@stream_socket_shutdown($this->stream,STREAM_SHUT_RDWR);
stream_set_blocking($this->stream,false);
}
function getRemoteAddress(){
return$this->parseAddress(@stream_socket_get_name($this->stream,true));
}
function getLocalAddress(){
return$this->parseAddress(@stream_socket_get_name($this->stream,false));
}
private function parseAddress($address){
if($address===false){
return;
}
if($this->unix){
if(substr($address,-1)===':'&&defined('HHVM_VERSION_ID')&&HHVM_VERSION_ID<31900){
$address=(string)substr($address,0,-1);
}
if($address===''||$address[0]==="\x00"){
return;
}
return'unix://'.$address;
}
$pos=strrpos($address,':');
if($pos!==false&&strpos($address,':')<$pos&&substr($address,0,1)!=='['){
$port=substr($address,$pos+1);
$address='['.substr($address,0,$pos).']:'.$port;
}
return($this->encryptionEnabled?'tls':'tcp').'://'.$address;
}
}
namespace React\Socket;
use React\Dns\Config\Config;
use React\Dns\Resolver\Factory;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\Promise;
use RuntimeException;
final class Connector implements ConnectorInterface
{
private$connectors=array();
function __construct(LoopInterface$loop,array$options=array()){
$options+=array('tcp'=>true,'tls'=>true,'unix'=>true,'dns'=>true,'timeout'=>true,);
if($options['timeout']===true){
$options['timeout']=(float)ini_get("default_socket_timeout");
}
if($options['tcp']instanceof ConnectorInterface){
$tcp=$options['tcp'];
}else{
$tcp=new TcpConnector($loop,is_array($options['tcp'])?$options['tcp']:array());
}
if($options['dns']!==false){
if($options['dns']instanceof Resolver){
$resolver=$options['dns'];
}else{
if($options['dns']!==true){
$server=$options['dns'];
}else{
$config=Config::loadSystemConfigBlocking();
$server=$config->nameservers?reset($config->nameservers):'8.8.8.8';
}
$factory=new Factory;
$resolver=$factory->create($server,$loop
);
}
$tcp=new DnsConnector($tcp,$resolver);
}
if($options['tcp']!==false){
$options['tcp']=$tcp;
if($options['timeout']!==false){
$options['tcp']=new TimeoutConnector($options['tcp'],$options['timeout'],$loop
);
}
$this->connectors['tcp']=$options['tcp'];
}
if($options['tls']!==false){
if(!$options['tls']instanceof ConnectorInterface){
$options['tls']=new SecureConnector($tcp,$loop,is_array($options['tls'])?$options['tls']:array());
}
if($options['timeout']!==false){
$options['tls']=new TimeoutConnector($options['tls'],$options['timeout'],$loop
);
}
$this->connectors['tls']=$options['tls'];
}
if($options['unix']!==false){
if(!$options['unix']instanceof ConnectorInterface){
$options['unix']=new UnixConnector($loop);
}
$this->connectors['unix']=$options['unix'];
}
}
function connect($uri){
$scheme='tcp';
if(strpos($uri,'://')!==false){
$scheme=(string)substr($uri,0,strpos($uri,'://'));
}
if(!isset($this->connectors[$scheme])){
return Promise\reject(new RuntimeException('No connector available for URI scheme "'.$scheme.'"'));
}
return$this->connectors[$scheme]->connect($uri);
}
}
namespace React\Socket;
use React\Dns\Resolver\Resolver;
use React\Promise;
use React\Promise\CancellablePromiseInterface;
use InvalidArgumentException;
use RuntimeException;
final class DnsConnector implements ConnectorInterface
{
private$connector;
private$resolver;
function __construct(ConnectorInterface$connector,Resolver$resolver){
$this->connector=$connector;
$this->resolver=$resolver;
}
function connect($uri){
if(strpos($uri,'://')===false){
$parts=parse_url('tcp://'.$uri);
unset($parts['scheme']);
}else{
$parts=parse_url($uri);
}
if(!$parts||!isset($parts['host'])){
return Promise\reject(new InvalidArgumentException('Given URI "'.$uri.'" is invalid'));
}
$host=trim($parts['host'],'[]');
$connector=$this->connector;
if(false!==filter_var($host,FILTER_VALIDATE_IP)){
return$connector->connect($uri);
}
return$this
->resolveHostname($host)->then(function($ip)use($connector,$host,$parts){
$uri='';
if(isset($parts['scheme'])){
$uri.=$parts['scheme'].'://';
}
if(strpos($ip,':')!==false){
$uri.='['.$ip.']';
}else{
$uri.=$ip;
}
if(isset($parts['port'])){
$uri.=':'.$parts['port'];
}
if(isset($parts['path'])){
$uri.=$parts['path'];
}
if(isset($parts['query'])){
$uri.='?'.$parts['query'];
}
$args=array();
parse_str(isset($parts['query'])?$parts['query']:'',$args);
if($host!==$ip&&!isset($args['hostname'])){
$uri.=(isset($parts['query'])?'&':'?').'hostname='.rawurlencode($host);
}
if(isset($parts['fragment'])){
$uri.='#'.$parts['fragment'];
}
return$connector->connect($uri);
});
}
private function resolveHostname($host){
$promise=$this->resolver->resolve($host);
return new Promise\Promise(function($resolve,$reject)use($promise){
$promise->then($resolve,$reject);
},function($_,$reject)use($promise){
$reject(new RuntimeException('Connection attempt cancelled during DNS lookup'));
if($promise instanceof CancellablePromiseInterface){
$promise->cancel();
}
}
);
}
}
namespace React\Socket;
class FixedUriConnector implements ConnectorInterface
{
private$uri;
private$connector;
function __construct($uri,ConnectorInterface$connector){
$this->uri=$uri;
$this->connector=$connector;
}
function connect($_){
return$this->connector->connect($this->uri);
}
}
namespace React\Socket;
use Evenement\EventEmitter;
use Exception;
use OverflowException;
class LimitingServer extends EventEmitter implements ServerInterface
{
private$connections=array();
private$server;
private$limit;
private$pauseOnLimit=false;
private$autoPaused=false;
private$manuPaused=false;
function __construct(ServerInterface$server,$connectionLimit,$pauseOnLimit=false){
$this->server=$server;
$this->limit=$connectionLimit;
if($connectionLimit!==null){
$this->pauseOnLimit=$pauseOnLimit;
}
$this->server->on('connection',array($this,'handleConnection'));
$this->server->on('error',array($this,'handleError'));
}
function getConnections(){
return$this->connections;
}
function getAddress(){
return$this->server->getAddress();
}
function pause(){
if(!$this->manuPaused){
$this->manuPaused=true;
if(!$this->autoPaused){
$this->server->pause();
}
}
}
function resume(){
if($this->manuPaused){
$this->manuPaused=false;
if(!$this->autoPaused){
$this->server->resume();
}
}
}
function close(){
$this->server->close();
}
function handleConnection(ConnectionInterface$connection){
if($this->limit!==null&&count($this->connections)>=$this->limit){
$this->handleError(new OverflowException('Connection closed because server reached connection limit'));
$connection->close();
return;
}
$this->connections[]=$connection;
$that=$this;
$connection->on('close',function()use($that,$connection){
$that->handleDisconnection($connection);
});
if($this->pauseOnLimit&&!$this->autoPaused&&count($this->connections)>=$this->limit){
$this->autoPaused=true;
if(!$this->manuPaused){
$this->server->pause();
}
}
$this->emit('connection',array($connection));
}
function handleDisconnection(ConnectionInterface$connection){
unset($this->connections[array_search($connection,$this->connections)]);
if($this->autoPaused&&count($this->connections)<$this->limit){
$this->autoPaused=false;
if(!$this->manuPaused){
$this->server->resume();
}
}
}
function handleError(Exception$error){
$this->emit('error',array($error));
}
}
namespace React\Socket;
use React\EventLoop\LoopInterface;
use React\Promise;
use BadMethodCallException;
use InvalidArgumentException;
use UnexpectedValueException;
final class SecureConnector implements ConnectorInterface
{
private$connector;
private$streamEncryption;
private$context;
function __construct(ConnectorInterface$connector,LoopInterface$loop,array$context=array()){
$this->connector=$connector;
$this->streamEncryption=new StreamEncryption($loop,false);
$this->context=$context;
}
function connect($uri){
if(!function_exists('stream_socket_enable_crypto')){
return Promise\reject(new BadMethodCallException('Encryption not supported on your platform (HHVM < 3.8?)'));}
if(strpos($uri,'://')===false){
$uri='tls://'.$uri;
}
$parts=parse_url($uri);
if(!$parts||!isset($parts['scheme'])||$parts['scheme']!=='tls'){
return Promise\reject(new InvalidArgumentException('Given URI "'.$uri.'" is invalid'));
}
$uri=str_replace('tls://','',$uri);
$context=$this->context;
$encryption=$this->streamEncryption;
return$this->connector->connect($uri)->then(function(ConnectionInterface$connection)use($context,$encryption){
if(!$connection instanceof Connection){
$connection->close();
throw new UnexpectedValueException('Base connector does not use internal Connection class exposing stream resource');
}
foreach($context as$name=>$value){
stream_context_set_option($connection->stream,'ssl',$name,$value);
}
return$encryption->enable($connection)->then(null,function($error)use($connection){
$connection->close();
throw$error;
});
});
}
}
namespace React\Socket;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use BadMethodCallException;
use UnexpectedValueException;
final class SecureServer extends EventEmitter implements ServerInterface
{
private$tcp;
private$encryption;
private$context;
function __construct(ServerInterface$tcp,LoopInterface$loop,array$context){
if(!function_exists('stream_socket_enable_crypto')){
throw new BadMethodCallException('Encryption not supported on your platform (HHVM < 3.8?)');}
$context+=array('passphrase'=>'');
$this->tcp=$tcp;
$this->encryption=new StreamEncryption($loop);
$this->context=$context;
$that=$this;
$this->tcp->on('connection',function($connection)use($that){
$that->handleConnection($connection);
});
$this->tcp->on('error',function($error)use($that){
$that->emit('error',array($error));
});
}
function getAddress(){
$address=$this->tcp->getAddress();
if($address===null){
return;
}
return str_replace('tcp://','tls://',$address);
}
function pause(){
$this->tcp->pause();
}
function resume(){
$this->tcp->resume();
}
function close(){
return$this->tcp->close();
}
function handleConnection(ConnectionInterface$connection){
if(!$connection instanceof Connection){
$this->emit('error',array(new UnexpectedValueException('Base server does not use internal Connection class exposing stream resource')));
$connection->end();
return;
}
foreach($this->context as$name=>$value){
stream_context_set_option($connection->stream,'ssl',$name,$value);
}
$that=$this;
$this->encryption->enable($connection)->then(function($conn)use($that){
$that->emit('connection',array($conn));
},function($error)use($that,$connection){
$that->emit('error',array($error));
$connection->end();
}
);
}
}
namespace React\Socket;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use Exception;
final class Server extends EventEmitter implements ServerInterface
{
private$server;
function __construct($uri,LoopInterface$loop,array$context=array()){
if($context&&(!isset($context['tcp'])&&!isset($context['tls'])&&!isset($context['unix']))){
$context=array('tcp'=>$context);
}
$context+=array('tcp'=>array(),'tls'=>array(),'unix'=>array());
$scheme='tcp';
$pos=strpos($uri,'://');
if($pos!==false){
$scheme=substr($uri,0,$pos);
}
if($scheme==='unix'){
$server=new UnixServer($uri,$loop,$context['unix']);
}else{
$server=new TcpServer(str_replace('tls://','',$uri),$loop,$context['tcp']);
if($scheme==='tls'){
$server=new SecureServer($server,$loop,$context['tls']);
}
}
$this->server=$server;
$that=$this;
$server->on('connection',function(ConnectionInterface$conn)use($that){
$that->emit('connection',array($conn));
});
$server->on('error',function(Exception$error)use($that){
$that->emit('error',array($error));
});
}
function getAddress(){
return$this->server->getAddress();
}
function pause(){
$this->server->pause();
}
function resume(){
$this->server->resume();
}
function close(){
$this->server->close();
}
}
namespace React\Socket;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use RuntimeException;
use UnexpectedValueException;
class StreamEncryption
{
private$loop;
private$method;
private$server;
private$errstr;
private$errno;
function __construct(LoopInterface$loop,$server=true){
$this->loop=$loop;
$this->server=$server;
if($server){
$this->method=STREAM_CRYPTO_METHOD_TLS_SERVER;
if(defined('STREAM_CRYPTO_METHOD_TLSv1_0_SERVER')){
$this->method|=STREAM_CRYPTO_METHOD_TLSv1_0_SERVER;
}
if(defined('STREAM_CRYPTO_METHOD_TLSv1_1_SERVER')){
$this->method|=STREAM_CRYPTO_METHOD_TLSv1_1_SERVER;
}
if(defined('STREAM_CRYPTO_METHOD_TLSv1_2_SERVER')){
$this->method|=STREAM_CRYPTO_METHOD_TLSv1_2_SERVER;
}
}else{
$this->method=STREAM_CRYPTO_METHOD_TLS_CLIENT;
if(defined('STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT')){
$this->method|=STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT;
}
if(defined('STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT')){
$this->method|=STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
}
if(defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')){
$this->method|=STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
}
}
}
function enable(Connection$stream){
return$this->toggle($stream,true);
}
function disable(Connection$stream){
return$this->toggle($stream,false);
}
function toggle(Connection$stream,$toggle){
$stream->pause();
$deferred=new Deferred(function($_,$reject)use($toggle){
$reject(new RuntimeException('Cancelled toggling encryption '.$toggle?'on':'off'));
});
$socket=$stream->stream;
$method=$this->method;
$context=stream_context_get_options($socket);
if(isset($context['ssl']['crypto_method'])){
$method=$context['ssl']['crypto_method'];
}
$that=$this;
$toggleCrypto=function()use($socket,$deferred,$toggle,$method,$that){
$that->toggleCrypto($socket,$deferred,$toggle,$method);
};
$this->loop->addReadStream($socket,$toggleCrypto);
if(!$this->server){
$toggleCrypto();
}
$loop=$this->loop;
return$deferred->promise()->then(function()use($stream,$socket,$loop,$toggle){
$loop->removeReadStream($socket);
$stream->encryptionEnabled=$toggle;
$stream->resume();
return$stream;
},function($error)use($stream,$socket,$loop){
$loop->removeReadStream($socket);
$stream->resume();
throw$error;
});
}
function toggleCrypto($socket,Deferred$deferred,$toggle,$method){
set_error_handler(array($this,'handleError'));
$result=stream_socket_enable_crypto($socket,$toggle,$method);
restore_error_handler();
if(true===$result){
$deferred->resolve();
}else if(false===$result){
$deferred->reject(new UnexpectedValueException(sprintf("Unable to complete SSL/TLS handshake: %s",$this->errstr),$this->errno
));
}else{
}
}
function handleError($errno,$errstr){
$this->errstr=str_replace(array("\r","\n"),' ',$errstr);
$this->errno=$errno;
}
}
namespace React\Socket;
use React\EventLoop\LoopInterface;
use React\Promise;
use InvalidArgumentException;
use RuntimeException;
final class TcpConnector implements ConnectorInterface
{
private$loop;
private$context;
function __construct(LoopInterface$loop,array$context=array()){
$this->loop=$loop;
$this->context=$context;
}
function connect($uri){
if(strpos($uri,'://')===false){
$uri='tcp://'.$uri;
}
$parts=parse_url($uri);
if(!$parts||!isset($parts['scheme'],$parts['host'],$parts['port'])||$parts['scheme']!=='tcp'){
return Promise\reject(new InvalidArgumentException('Given URI "'.$uri.'" is invalid'));
}
$ip=trim($parts['host'],'[]');
if(false===filter_var($ip,FILTER_VALIDATE_IP)){
return Promise\reject(new InvalidArgumentException('Given URI "'.$ip.'" does not contain a valid host IP'));
}
$context=array('socket'=>$this->context
);
$args=array();
if(isset($parts['query'])){
parse_str($parts['query'],$args);
}
if(isset($args['hostname'])){
$context['ssl']=array('SNI_enabled'=>true,'peer_name'=>$args['hostname']);
if(PHP_VERSION_ID<50600){
$context['ssl']+=array('SNI_server_name'=>$args['hostname'],'CN_match'=>$args['hostname']);
}
}
$remote='tcp://'.$parts['host'].':'.$parts['port'];
$socket=@stream_socket_client($remote,$errno,$errstr,0,STREAM_CLIENT_CONNECT|STREAM_CLIENT_ASYNC_CONNECT,stream_context_create($context));
if(false===$socket){
return Promise\reject(new RuntimeException(sprintf("Connection to %s failed: %s",$uri,$errstr),$errno
));
}
stream_set_blocking($socket,0);
return$this->waitForStreamOnce($socket);
}
private function waitForStreamOnce($stream){
$loop=$this->loop;
return new Promise\Promise(function($resolve,$reject)use($loop,$stream){
$loop->addWriteStream($stream,function($stream)use($loop,$resolve,$reject){
$loop->removeWriteStream($stream);
if(false===stream_socket_get_name($stream,true)){
fclose($stream);
$reject(new RuntimeException('Connection refused'));
}else{
$resolve(new Connection($stream,$loop));
}
});
},function()use($loop,$stream){
$loop->removeWriteStream($stream);
fclose($stream);
if(PHP_VERSION_ID<50400&&is_resource($stream)){
fclose($stream);
}
throw new RuntimeException('Cancelled while waiting for TCP/IP connection to be established');
});
}
}
namespace React\Socket;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use InvalidArgumentException;
use RuntimeException;
final class TcpServer extends EventEmitter implements ServerInterface
{
private$master;
private$loop;
private$listening=false;
function __construct($uri,LoopInterface$loop,array$context=array()){
$this->loop=$loop;
if((string)(int)$uri===(string)$uri){
$uri='127.0.0.1:'.$uri;
}
if(strpos($uri,'://')===false){
$uri='tcp://'.$uri;
}
if(substr($uri,-2)===':0'){
$parts=parse_url(substr($uri,0,-2));
if($parts){
$parts['port']=0;
}
}else{
$parts=parse_url($uri);
}
if(!$parts||!isset($parts['scheme'],$parts['host'],$parts['port'])||$parts['scheme']!=='tcp'){
throw new InvalidArgumentException('Invalid URI "'.$uri.'" given');
}
if(false===filter_var(trim($parts['host'],'[]'),FILTER_VALIDATE_IP)){
throw new InvalidArgumentException('Given URI "'.$uri.'" does not contain a valid host IP');
}
$this->master=@stream_socket_server($uri,$errno,$errstr,STREAM_SERVER_BIND|STREAM_SERVER_LISTEN,stream_context_create(array('socket'=>$context)));
if(false===$this->master){
throw new RuntimeException('Failed to listen on "'.$uri.'": '.$errstr,$errno);
}
stream_set_blocking($this->master,0);
$this->resume();
}
function getAddress(){
if(!is_resource($this->master)){
return;
}
$address=stream_socket_get_name($this->master,false);
$pos=strrpos($address,':');
if($pos!==false&&strpos($address,':')<$pos&&substr($address,0,1)!=='['){
$port=substr($address,$pos+1);
$address='['.substr($address,0,$pos).']:'.$port;
}
return'tcp://'.$address;
}
function pause(){
if(!$this->listening){
return;
}
$this->loop->removeReadStream($this->master);
$this->listening=false;
}
function resume(){
if($this->listening||!is_resource($this->master)){
return;
}
$that=$this;
$this->loop->addReadStream($this->master,function($master)use($that){
$newSocket=@stream_socket_accept($master);
if(false===$newSocket){
$that->emit('error',array(new RuntimeException('Error accepting new connection')));
return;
}
$that->handleConnection($newSocket);
});
$this->listening=true;
}
function close(){
if(!is_resource($this->master)){
return;
}
$this->pause();
fclose($this->master);
$this->removeAllListeners();
}
function handleConnection($socket){
$this->emit('connection',array(new Connection($socket,$this->loop)));
}
}
namespace React\Socket;
use React\EventLoop\LoopInterface;
use React\Promise\Timer;
final class TimeoutConnector implements ConnectorInterface
{
private$connector;
private$timeout;
private$loop;
function __construct(ConnectorInterface$connector,$timeout,LoopInterface$loop){
$this->connector=$connector;
$this->timeout=$timeout;
$this->loop=$loop;
}
function connect($uri){
return Timer\timeout($this->connector->connect($uri),$this->timeout,$this->loop);
}
}
namespace React\Socket;
use React\EventLoop\LoopInterface;
use React\Promise;
use InvalidArgumentException;
use RuntimeException;
final class UnixConnector implements ConnectorInterface
{
private$loop;
function __construct(LoopInterface$loop){
$this->loop=$loop;
}
function connect($path){
if(strpos($path,'://')===false){
$path='unix://'.$path;
}elseif(substr($path,0,7)!=='unix://'){
return Promise\reject(new InvalidArgumentException('Given URI "'.$path.'" is invalid'));
}
$resource=@stream_socket_client($path,$errno,$errstr,1.0);
if(!$resource){
return Promise\reject(new RuntimeException('Unable to connect to unix domain socket "'.$path.'": '.$errstr,$errno));
}
$connection=new Connection($resource,$this->loop);
$connection->unix=true;
return Promise\resolve($connection);
}
}
namespace React\Socket;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use InvalidArgumentException;
use RuntimeException;
final class UnixServer extends EventEmitter implements ServerInterface
{
private$master;
private$loop;
private$listening=false;
function __construct($path,LoopInterface$loop,array$context=array()){
$this->loop=$loop;
if(strpos($path,'://')===false){
$path='unix://'.$path;
}elseif(substr($path,0,7)!=='unix://'){
throw new InvalidArgumentException('Given URI "'.$path.'" is invalid');
}
$this->master=@stream_socket_server($path,$errno,$errstr,STREAM_SERVER_BIND|STREAM_SERVER_LISTEN,stream_context_create(array('socket'=>$context)));
if(false===$this->master){
throw new RuntimeException('Failed to listen on unix domain socket "'.$path.'": '.$errstr,$errno);
}
stream_set_blocking($this->master,0);
$this->resume();
}
function getAddress(){
if(!is_resource($this->master)){
return;
}
return'unix://'.stream_socket_get_name($this->master,false);
}
function pause(){
if(!$this->listening){
return;
}
$this->loop->removeReadStream($this->master);
$this->listening=false;
}
function resume(){
if($this->listening||!is_resource($this->master)){
return;
}
$that=$this;
$this->loop->addReadStream($this->master,function($master)use($that){
$newSocket=@stream_socket_accept($master);
if(false===$newSocket){
$that->emit('error',array(new RuntimeException('Error accepting new connection')));
return;
}
$that->handleConnection($newSocket);
});
$this->listening=true;
}
function close(){
if(!is_resource($this->master)){
return;
}
$this->pause();
fclose($this->master);
$this->removeAllListeners();
}
function handleConnection($socket){
$connection=new Connection($socket,$this->loop);
$connection->unix=true;
$this->emit('connection',array($connection
));
}
}
namespace React\Stream;
use Evenement\EventEmitter;
final class CompositeStream extends EventEmitter implements DuplexStreamInterface
{
private$readable;
private$writable;
private$closed=false;
function __construct(ReadableStreamInterface$readable,WritableStreamInterface$writable){
$this->readable=$readable;
$this->writable=$writable;
if(!$readable->isReadable()||!$writable->isWritable()){
return$this->close();
}
Util::forwardEvents($this->readable,$this,array('data','end','error'));
Util::forwardEvents($this->writable,$this,array('drain','error','pipe'));
$this->readable->on('close',array($this,'close'));
$this->writable->on('close',array($this,'close'));
}
function isReadable(){
return$this->readable->isReadable();
}
function pause(){
$this->readable->pause();
}
function resume(){
if(!$this->writable->isWritable()){
return;
}
$this->readable->resume();
}
function pipe(WritableStreamInterface$dest,array$options=array()){
return Util::pipe($this,$dest,$options);
}
function isWritable(){
return$this->writable->isWritable();
}
function write($data){
return$this->writable->write($data);
}
function end($data=null){
$this->readable->pause();
$this->writable->end($data);
}
function close(){
if($this->closed){
return;
}
$this->closed=true;
$this->readable->close();
$this->writable->close();
$this->emit('close');
$this->removeAllListeners();
}
}
namespace React\Stream;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use InvalidArgumentException;
final class DuplexResourceStream extends EventEmitter implements DuplexStreamInterface
{
private$stream;
private$loop;
private$bufferSize;
private$buffer;
private$readable=true;
private$writable=true;
private$closing=false;
private$listening=false;
function __construct($stream,LoopInterface$loop,$readChunkSize=null,WritableStreamInterface$buffer=null){
if(!is_resource($stream)||get_resource_type($stream)!=="stream"){
throw new InvalidArgumentException('First parameter must be a valid stream resource');
}
$meta=stream_get_meta_data($stream);
if(isset($meta['mode'])&&$meta['mode']!==''&&strpos($meta['mode'],'+')===false){
throw new InvalidArgumentException('Given stream resource is not opened in read and write mode');
}
if(stream_set_blocking($stream,0)!==true){
throw new\RuntimeException('Unable to set stream resource to non-blocking mode');
}
if(function_exists('stream_set_read_buffer')&&!$this->isLegacyPipe($stream)){
stream_set_read_buffer($stream,0);
}
if($buffer===null){
$buffer=new WritableResourceStream($stream,$loop);
}
$this->stream=$stream;
$this->loop=$loop;
$this->bufferSize=($readChunkSize===null)?65536:(int)$readChunkSize;
$this->buffer=$buffer;
$that=$this;
$this->buffer->on('error',function($error)use($that){
$that->emit('error',array($error));
});
$this->buffer->on('close',array($this,'close'));
$this->buffer->on('drain',function()use($that){
$that->emit('drain');
});
$this->resume();
}
function isReadable(){
return$this->readable;
}
function isWritable(){
return$this->writable;
}
function pause(){
if($this->listening){
$this->loop->removeReadStream($this->stream);
$this->listening=false;
}
}
function resume(){
if(!$this->listening&&$this->readable){
$this->loop->addReadStream($this->stream,array($this,'handleData'));
$this->listening=true;
}
}
function write($data){
if(!$this->writable){
return false;
}
return$this->buffer->write($data);
}
function close(){
if(!$this->writable&&!$this->closing){
return;
}
$this->closing=false;
$this->readable=false;
$this->writable=false;
$this->emit('close');
$this->pause();
$this->buffer->close();
$this->removeAllListeners();
if(is_resource($this->stream)){
fclose($this->stream);
}
}
function end($data=null){
if(!$this->writable){
return;
}
$this->closing=true;
$this->readable=false;
$this->writable=false;
$this->pause();
$this->buffer->end($data);
}
function pipe(WritableStreamInterface$dest,array$options=array()){
return Util::pipe($this,$dest,$options);
}
function handleData($stream){
$error=null;
set_error_handler(function($errno,$errstr,$errfile,$errline)use(&$error){
$error=new\ErrorException($errstr,0,$errno,$errfile,$errline
);
});
$data=stream_get_contents($stream,$this->bufferSize);
restore_error_handler();
if($error!==null){
$this->emit('error',array(new\RuntimeException('Unable to read from stream: '.$error->getMessage(),0,$error)));
$this->close();
return;
}
if($data!==''){
$this->emit('data',array($data));
}else{
$this->emit('end');
$this->close();
}
}
private function isLegacyPipe($resource){
if(PHP_VERSION_ID<50428||(PHP_VERSION_ID>=50500&&PHP_VERSION_ID<50512)){
$meta=stream_get_meta_data($resource);
if(isset($meta['stream_type'])&&$meta['stream_type']==='STDIO'){
return true;
}
}
return false;
}
}
namespace React\Stream;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use InvalidArgumentException;
final class ReadableResourceStream extends EventEmitter implements ReadableStreamInterface
{
private$stream;
private$loop;
private$bufferSize;
private$closed=false;
private$listening=false;
function __construct($stream,LoopInterface$loop,$readChunkSize=null){
if(!is_resource($stream)||get_resource_type($stream)!=="stream"){
throw new InvalidArgumentException('First parameter must be a valid stream resource');
}
$meta=stream_get_meta_data($stream);
if(isset($meta['mode'])&&$meta['mode']!==''&&strpos($meta['mode'],'r')===strpos($meta['mode'],'+')){
throw new InvalidArgumentException('Given stream resource is not opened in read mode');
}
if(stream_set_blocking($stream,0)!==true){
throw new\RuntimeException('Unable to set stream resource to non-blocking mode');
}
if(function_exists('stream_set_read_buffer')&&!$this->isLegacyPipe($stream)){
stream_set_read_buffer($stream,0);
}
$this->stream=$stream;
$this->loop=$loop;
$this->bufferSize=($readChunkSize===null)?65536:(int)$readChunkSize;
$this->resume();
}
function isReadable(){
return!$this->closed;
}
function pause(){
if($this->listening){
$this->loop->removeReadStream($this->stream);
$this->listening=false;
}
}
function resume(){
if(!$this->listening&&!$this->closed){
$this->loop->addReadStream($this->stream,array($this,'handleData'));
$this->listening=true;
}
}
function pipe(WritableStreamInterface$dest,array$options=array()){
return Util::pipe($this,$dest,$options);
}
function close(){
if($this->closed){
return;
}
$this->closed=true;
$this->emit('close');
$this->pause();
$this->removeAllListeners();
if(is_resource($this->stream)){
fclose($this->stream);
}
}
function handleData(){
$error=null;
set_error_handler(function($errno,$errstr,$errfile,$errline)use(&$error){
$error=new\ErrorException($errstr,0,$errno,$errfile,$errline
);
});
$data=stream_get_contents($this->stream,$this->bufferSize);
restore_error_handler();
if($error!==null){
$this->emit('error',array(new\RuntimeException('Unable to read from stream: '.$error->getMessage(),0,$error)));
$this->close();
return;
}
if($data!==''){
$this->emit('data',array($data));
}else{
$this->emit('end');
$this->close();
}
}
private function isLegacyPipe($resource){
if(PHP_VERSION_ID<50428||(PHP_VERSION_ID>=50500&&PHP_VERSION_ID<50512)){
$meta=stream_get_meta_data($resource);
if(isset($meta['stream_type'])&&$meta['stream_type']==='STDIO'){
return true;
}
}
return false;
}
}
namespace React\Stream;
use Evenement\EventEmitter;
use InvalidArgumentException;
final class ThroughStream extends EventEmitter implements DuplexStreamInterface
{
private$readable=true;
private$writable=true;
private$closed=false;
private$paused=false;
private$drain=false;
private$callback;
function __construct($callback=null){
if($callback!==null&&!is_callable($callback)){
throw new InvalidArgumentException('Invalid transformation callback given');
}
$this->callback=$callback;
}
function pause(){
$this->paused=true;
}
function resume(){
if($this->drain){
$this->drain=false;
$this->emit('drain');
}
$this->paused=false;
}
function pipe(WritableStreamInterface$dest,array$options=array()){
return Util::pipe($this,$dest,$options);
}
function isReadable(){
return$this->readable;
}
function isWritable(){
return$this->writable;
}
function write($data){
if(!$this->writable){
return false;
}
if($this->callback!==null){
try{
$data=call_user_func($this->callback,$data);
}catch(\Exception$e){
$this->emit('error',array($e));
$this->close();
return false;
}
}
$this->emit('data',array($data));
if($this->paused){
$this->drain=true;
return false;
}
return true;
}
function end($data=null){
if(!$this->writable){
return;
}
if(null!==$data){
$this->write($data);
if(!$this->writable){
return;
}
}
$this->readable=false;
$this->writable=false;
$this->paused=true;
$this->drain=false;
$this->emit('end');
$this->close();
}
function close(){
if($this->closed){
return;
}
$this->readable=false;
$this->writable=false;
$this->closed=true;
$this->paused=true;
$this->drain=false;
$this->callback=null;
$this->emit('close');
$this->removeAllListeners();
}
}
namespace React\Stream;
final class Util
{
static function pipe(ReadableStreamInterface$source,WritableStreamInterface$dest,array$options=array()){
if(!$source->isReadable()){
return$dest;
}
if(!$dest->isWritable()){
$source->pause();
return$dest;
}
$dest->emit('pipe',array($source));
$source->on('data',$dataer=function($data)use($source,$dest){
$feedMore=$dest->write($data);
if(false===$feedMore){
$source->pause();
}
});
$dest->on('close',function()use($source,$dataer){
$source->removeListener('data',$dataer);
$source->pause();
});
$dest->on('drain',$drainer=function()use($source){
$source->resume();
});
$source->on('close',function()use($dest,$drainer){
$dest->removeListener('drain',$drainer);
});
$end=isset($options['end'])?$options['end']:true;
if($end){
$source->on('end',$ender=function()use($dest){
$dest->end();
});
$dest->on('close',function()use($source,$ender){
$source->removeListener('end',$ender);
});
}
return$dest;
}
static function forwardEvents($source,$target,array$events){
foreach($events as$event){
$source->on($event,function()use($event,$target){
$target->emit($event,func_get_args());
});
}
}
}
namespace React\Stream;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
final class WritableResourceStream extends EventEmitter implements WritableStreamInterface
{
private$stream;
private$loop;
private$softLimit;
private$writeChunkSize;
private$listening=false;
private$writable=true;
private$closed=false;
private$data='';
function __construct($stream,LoopInterface$loop,$writeBufferSoftLimit=null,$writeChunkSize=null){
if(!is_resource($stream)||get_resource_type($stream)!=="stream"){
throw new\InvalidArgumentException('First parameter must be a valid stream resource');
}
$meta=stream_get_meta_data($stream);
if(isset($meta['mode'])&&$meta['mode']!==''&&strtr($meta['mode'],'waxc+','.....')===$meta['mode']){
throw new\InvalidArgumentException('Given stream resource is not opened in write mode');
}
if(stream_set_blocking($stream,0)!==true){
throw new\RuntimeException('Unable to set stream resource to non-blocking mode');
}
$this->stream=$stream;
$this->loop=$loop;
$this->softLimit=($writeBufferSoftLimit===null)?65536:(int)$writeBufferSoftLimit;
$this->writeChunkSize=($writeChunkSize===null)?-1:(int)$writeChunkSize;
}
function isWritable(){
return$this->writable;
}
function write($data){
if(!$this->writable){
return false;
}
$this->data.=$data;
if(!$this->listening&&$this->data!==''){
$this->listening=true;
$this->loop->addWriteStream($this->stream,array($this,'handleWrite'));
}
return!isset($this->data[$this->softLimit-1]);
}
function end($data=null){
if(null!==$data){
$this->write($data);
}
$this->writable=false;
if($this->data===''){
$this->close();
}
}
function close(){
if($this->closed){
return;
}
if($this->listening){
$this->listening=false;
$this->loop->removeWriteStream($this->stream);
}
$this->closed=true;
$this->writable=false;
$this->data='';
$this->emit('close');
$this->removeAllListeners();
if(is_resource($this->stream)){
fclose($this->stream);
}
}
function handleWrite(){
$error=null;
set_error_handler(function($errno,$errstr,$errfile,$errline)use(&$error){
$error=array('message'=>$errstr,'number'=>$errno,'file'=>$errfile,'line'=>$errline
);
});
if($this->writeChunkSize===-1){
$sent=fputs($this->stream,$this->data);
}else{
$sent=fputs($this->stream,$this->data,$this->writeChunkSize);
}
restore_error_handler();
if($sent===0||$sent===false){
if($error!==null){
$error=new\ErrorException($error['message'],0,$error['number'],$error['file'],$error['line']);
}
$this->emit('error',array(new\RuntimeException('Unable to write to stream: '.($error!==null?$error->getMessage():'Unknown error'),0,$error)));
$this->close();
return;
}
$exceeded=isset($this->data[$this->softLimit-1]);
$this->data=(string)substr($this->data,$sent);
if($exceeded&&!isset($this->data[$this->softLimit-1])){
$this->emit('drain');
}
if($this->data===''){
if($this->listening){
$this->loop->removeWriteStream($this->stream);
$this->listening=false;
}
if(!$this->writable){
$this->close();
}
}
}
}
namespace RingCentral\Psr7;
use Psr\Http\Message\StreamInterface;
class AppendStream implements StreamInterface
{
private$streams=array();
private$seekable=true;
private$current=0;
private$pos=0;
private$detached=false;
function __construct(array$streams=array()){
foreach($streams as$stream){
$this->addStream($stream);
}
}
function __toString(){
try{
$this->rewind();
return$this->getContents();
}catch(\Exception$e){
return'';
}
}
function addStream(StreamInterface$stream){
if(!$stream->isReadable()){
throw new\InvalidArgumentException('Each stream must be readable');
}
if(!$stream->isSeekable()){
$this->seekable=false;
}
$this->streams[]=$stream;
}
function getContents(){
return copy_to_string($this);
}
function close(){
$this->pos=$this->current=0;
foreach($this->streams as$stream){
$stream->close();
}
$this->streams=array();
}
function detach(){
$this->close();
$this->detached=true;
}
function tell(){
return$this->pos;
}
function getSize(){
$size=0;
foreach($this->streams as$stream){
$s=$stream->getSize();
if($s===null){
return;
}
$size+=$s;
}
return$size;
}
function eof(){
return!$this->streams||($this->current>=count($this->streams)-1&&
$this->streams[$this->current]->eof());
}
function rewind(){
$this->seek(0);
}
function seek($offset,$whence=SEEK_SET){
if(!$this->seekable){
throw new\RuntimeException('This AppendStream is not seekable');
}elseif($whence!==SEEK_SET){
throw new\RuntimeException('The AppendStream can only seek with SEEK_SET');
}
$this->pos=$this->current=0;
foreach($this->streams as$i=>$stream){
try{
$stream->rewind();
}catch(\Exception$e){
throw new\RuntimeException('Unable to seek stream '.$i.' of the AppendStream',0,$e);
}
}
while($this->pos<$offset&&!$this->eof()){
$result=$this->read(min(8096,$offset-$this->pos));
if($result===''){
break;
}
}
}
function read($length){
$buffer='';
$total=count($this->streams)-1;
$remaining=$length;
$progressToNext=false;
while($remaining>0){
if($progressToNext||$this->streams[$this->current]->eof()){
$progressToNext=false;
if($this->current===$total){
break;
}
$this->current++;
}
$result=$this->streams[$this->current]->read($remaining);
if($result==null){
$progressToNext=true;
continue;
}
$buffer.=$result;
$remaining=$length-strlen($buffer);
}
$this->pos+=strlen($buffer);
return$buffer;
}
function isReadable(){
return true;
}
function isWritable(){
return false;
}
function isSeekable(){
return$this->seekable;
}
function write($string){
throw new\RuntimeException('Cannot write to an AppendStream');
}
function getMetadata($key=null){
return$key?null:array();
}
}
namespace RingCentral\Psr7;
use Psr\Http\Message\StreamInterface;
class BufferStream implements StreamInterface
{
private$hwm;
private$buffer='';
function __construct($hwm=16384){
$this->hwm=$hwm;
}
function __toString(){
return$this->getContents();
}
function getContents(){
$buffer=$this->buffer;
$this->buffer='';
return$buffer;
}
function close(){
$this->buffer='';
}
function detach(){
$this->close();
}
function getSize(){
return strlen($this->buffer);
}
function isReadable(){
return true;
}
function isWritable(){
return true;
}
function isSeekable(){
return false;
}
function rewind(){
$this->seek(0);
}
function seek($offset,$whence=SEEK_SET){
throw new\RuntimeException('Cannot seek a BufferStream');
}
function eof(){
return strlen($this->buffer)===0;
}
function tell(){
throw new\RuntimeException('Cannot determine the position of a BufferStream');
}
function read($length){
$currentLength=strlen($this->buffer);
if($length>=$currentLength){
$result=$this->buffer;
$this->buffer='';
}else{
$result=substr($this->buffer,0,$length);
$this->buffer=substr($this->buffer,$length);
}
return$result;
}
function write($string){
$this->buffer.=$string;
if(strlen($this->buffer)>=$this->hwm){
return false;
}
return strlen($string);
}
function getMetadata($key=null){
if($key=='hwm'){
return$this->hwm;
}
return$key?null:array();
}
}
namespace RingCentral\Psr7;
use Psr\Http\Message\StreamInterface;
abstract class StreamDecoratorTrait implements StreamInterface
{
function __construct(StreamInterface$stream=null){
if($stream)$this->stream=$stream;
}
function __get($name){
if($name=='stream'){
$this->stream=$this->createStream();
return$this->stream;
}
throw new\UnexpectedValueException("$name not found on class");
}
function __toString(){
try{
if($this->isSeekable()){
$this->seek(0);
}
return$this->getContents();
}catch(\Exception$e){
trigger_error('StreamDecorator::__toString exception: '.(string)$e,E_USER_ERROR);
return'';
}
}
function getContents(){
return copy_to_string($this);
}
function __call($method,array$args){
$result=call_user_func_array(array($this->stream,$method),$args);
return$result===$this->stream?$this:$result;
}
function close(){
$this->stream->close();
}
function getMetadata($key=null){
return$this->stream->getMetadata($key);
}
function detach(){
return$this->stream->detach();
}
function getSize(){
return$this->stream->getSize();
}
function eof(){
return$this->stream->eof();
}
function tell(){
return$this->stream->tell();
}
function isReadable(){
return$this->stream->isReadable();
}
function isWritable(){
return$this->stream->isWritable();
}
function isSeekable(){
return$this->stream->isSeekable();
}
function rewind(){
$this->seek(0);
}
function seek($offset,$whence=SEEK_SET){
$this->stream->seek($offset,$whence);
}
function read($length){
return$this->stream->read($length);
}
function write($string){
return$this->stream->write($string);
}
}
namespace RingCentral\Psr7;
use Psr\Http\Message\StreamInterface;
class CachingStream extends StreamDecoratorTrait implements StreamInterface
{
private$remoteStream;
private$skipReadBytes=0;
function __construct(StreamInterface$stream,StreamInterface$target=null
){
$this->remoteStream=$stream;
parent::__construct($target?:new Stream(fopen('php://temp','r+')));
}
function getSize(){
return max($this->stream->getSize(),$this->remoteStream->getSize());
}
function rewind(){
$this->seek(0);
}
function seek($offset,$whence=SEEK_SET){
if($whence==SEEK_SET){
$byte=$offset;
}elseif($whence==SEEK_CUR){
$byte=$offset+$this->tell();
}elseif($whence==SEEK_END){
$size=$this->remoteStream->getSize();
if($size===null){
$size=$this->cacheEntireStream();
}
$byte=$size-1-$offset;
}else{
throw new\InvalidArgumentException('Invalid whence');
}
$diff=$byte-$this->stream->getSize();
if($diff>0){
$this->read($diff);
}else{
$this->stream->seek($byte);
}
}
function read($length){
$data=$this->stream->read($length);
$remaining=$length-strlen($data);
if($remaining){
$remoteData=$this->remoteStream->read($remaining+$this->skipReadBytes
);
if($this->skipReadBytes){
$len=strlen($remoteData);
$remoteData=substr($remoteData,$this->skipReadBytes);
$this->skipReadBytes=max(0,$this->skipReadBytes-$len);
}
$data.=$remoteData;
$this->stream->write($remoteData);
}
return$data;
}
function write($string){
$overflow=(strlen($string)+$this->tell())-$this->remoteStream->tell();
if($overflow>0){
$this->skipReadBytes+=$overflow;
}
return$this->stream->write($string);
}
function eof(){
return$this->stream->eof()&&$this->remoteStream->eof();
}
function close(){
$this->remoteStream->close()&&$this->stream->close();
}
private function cacheEntireStream(){
$target=new FnStream(array('write'=>'strlen'));
copy_to_stream($this,$target);
return$this->tell();
}
}
namespace RingCentral\Psr7;
use Psr\Http\Message\StreamInterface;
class DroppingStream extends StreamDecoratorTrait implements StreamInterface
{
private$maxLength;
function __construct(StreamInterface$stream,$maxLength){
parent::__construct($stream);
$this->maxLength=$maxLength;
}
function write($string){
$diff=$this->maxLength-$this->stream->getSize();
if($diff<=0){
return 0;
}
if(strlen($string)<$diff){
return$this->stream->write($string);
}
return$this->stream->write(substr($string,0,$diff));
}
}
namespace RingCentral\Psr7;
use Psr\Http\Message\StreamInterface;
class FnStream implements StreamInterface
{
private$methods;
private static$slots=array('__toString','close','detach','rewind','getSize','tell','eof','isSeekable','seek','isWritable','write','isReadable','read','getContents','getMetadata');
function __construct(array$methods){
$this->methods=$methods;
foreach($methods as$name=>$fn){
$this->{'_fn_'.$name}=$fn;
}
}
function __get($name){
throw new\BadMethodCallException(str_replace('_fn_','',$name).'() is not implemented in the FnStream');
}
function __destruct(){
if(isset($this->_fn_close)){
call_user_func($this->_fn_close);
}
}
static function decorate(StreamInterface$stream,array$methods){
foreach(array_diff(self::$slots,array_keys($methods))as$diff){
$methods[$diff]=array($stream,$diff);
}
return new self($methods);
}
function __toString(){
return call_user_func($this->_fn___toString);
}
function close(){
return call_user_func($this->_fn_close);
}
function detach(){
return call_user_func($this->_fn_detach);
}
function getSize(){
return call_user_func($this->_fn_getSize);
}
function tell(){
return call_user_func($this->_fn_tell);
}
function eof(){
return call_user_func($this->_fn_eof);
}
function isSeekable(){
return call_user_func($this->_fn_isSeekable);
}
function rewind(){
call_user_func($this->_fn_rewind);
}
function seek($offset,$whence=SEEK_SET){
call_user_func($this->_fn_seek,$offset,$whence);
}
function isWritable(){
return call_user_func($this->_fn_isWritable);
}
function write($string){
return call_user_func($this->_fn_write,$string);
}
function isReadable(){
return call_user_func($this->_fn_isReadable);
}
function read($length){
return call_user_func($this->_fn_read,$length);
}
function getContents(){
return call_user_func($this->_fn_getContents);
}
function getMetadata($key=null){
return call_user_func($this->_fn_getMetadata,$key);
}
}
namespace RingCentral\Psr7;
use Psr\Http\Message\StreamInterface;
class InflateStream extends StreamDecoratorTrait implements StreamInterface
{
function __construct(StreamInterface$stream){
$stream=new LimitStream($stream,-1,10);
$resource=StreamWrapper::getResource($stream);
stream_filter_append($resource,'zlib.inflate',STREAM_FILTER_READ);
parent::__construct(new Stream($resource));
}
}
namespace RingCentral\Psr7;
use Psr\Http\Message\StreamInterface;
class LazyOpenStream extends StreamDecoratorTrait implements StreamInterface
{
private$filename;
private$mode;
function __construct($filename,$mode){
$this->filename=$filename;
$this->mode=$mode;
parent::__construct();
}
protected function createStream(){
return stream_for(try_fopen($this->filename,$this->mode));
}
}
namespace RingCentral\Psr7;
use Psr\Http\Message\StreamInterface;
class LimitStream extends StreamDecoratorTrait implements StreamInterface
{
private$offset;
private$limit;
function __construct(StreamInterface$stream,$limit=-1,$offset=0
){
parent::__construct($stream);
$this->setLimit($limit);
$this->setOffset($offset);
}
function eof(){
if($this->stream->eof()){
return true;
}
if($this->limit==-1){
return false;
}
return$this->stream->tell()>=$this->offset+$this->limit;
}
function getSize(){
if(null===($length=$this->stream->getSize())){
return;
}elseif($this->limit==-1){
return$length-$this->offset;
}else{
return min($this->limit,$length-$this->offset);
}
}
function seek($offset,$whence=SEEK_SET){
if($whence!==SEEK_SET||$offset<0){
throw new\RuntimeException(sprintf('Cannot seek to offset % with whence %s',$offset,$whence
));
}
$offset+=$this->offset;
if($this->limit!==-1){
if($offset>$this->offset+$this->limit){
$offset=$this->offset+$this->limit;
}
}
$this->stream->seek($offset);
}
function tell(){
return$this->stream->tell()-$this->offset;
}
function setOffset($offset){
$current=$this->stream->tell();
if($current!==$offset){
if($this->stream->isSeekable()){
$this->stream->seek($offset);
}elseif($current>$offset){
throw new\RuntimeException("Could not seek to stream offset $offset");
}else{
$this->stream->read($offset-$current);
}
}
$this->offset=$offset;
}
function setLimit($limit){
$this->limit=$limit;
}
function read($length){
if($this->limit==-1){
return$this->stream->read($length);
}
$remaining=($this->offset+$this->limit)-$this->stream->tell();
if($remaining>0){
return$this->stream->read(min($remaining,$length));
}
return'';
}
}
namespace RingCentral\Psr7;
use Psr\Http\Message\StreamInterface;
class MultipartStream extends StreamDecoratorTrait implements StreamInterface
{
private$boundary;
function __construct(array$elements=array(),$boundary=null){
$this->boundary=$boundary?:uniqid();
parent::__construct($this->createStream($elements));
}
function getBoundary(){
return$this->boundary;
}
function isWritable(){
return false;
}
private function getHeaders(array$headers){
$str='';
foreach($headers as$key=>$value){
$str.="{$key}: {$value}\r\n";
}
return"--{$this->boundary}\r\n".trim($str)."\r\n\r\n";
}
protected function createStream(array$elements){
$stream=new AppendStream;
foreach($elements as$element){
$this->addElement($stream,$element);
}
$stream->addStream(stream_for("--{$this->boundary}--\r\n"));
return$stream;
}
private function addElement(AppendStream$stream,array$element){
foreach(array('contents','name')as$key){
if(!key_exists($key,$element)){
throw new\InvalidArgumentException("A '{$key}' key is required");
}
}
$element['contents']=stream_for($element['contents']);
if(empty($element['filename'])){
$uri=$element['contents']->getMetadata('uri');
if(substr($uri,0,6)!=='php://'){
$element['filename']=$uri;
}
}
list($body,$headers)=$this->createElement($element['name'],$element['contents'],isset($element['filename'])?$element['filename']:null,isset($element['headers'])?$element['headers']:array());
$stream->addStream(stream_for($this->getHeaders($headers)));
$stream->addStream($body);
$stream->addStream(stream_for("\r\n"));
}
private function createElement($name,$stream,$filename,array$headers){
$disposition=$this->getHeader($headers,'content-disposition');
if(!$disposition){
$headers['Content-Disposition']=$filename
?sprintf('form-data; name="%s"; filename="%s"',$name,basename($filename)):"form-data; name=\"{$name}\"";
}
$length=$this->getHeader($headers,'content-length');
if(!$length){
if($length=$stream->getSize()){
$headers['Content-Length']=(string)$length;
}
}
$type=$this->getHeader($headers,'content-type');
if(!$type&&$filename){
if($type=mimetype_from_filename($filename)){
$headers['Content-Type']=$type;
}
}
return array($stream,$headers);
}
private function getHeader(array$headers,$key){
$lowercaseHeader=strtolower($key);
foreach($headers as$k=>$v){
if(strtolower($k)===$lowercaseHeader){
return$v;
}
}
return;
}
}
namespace RingCentral\Psr7;
use Psr\Http\Message\StreamInterface;
class NoSeekStream extends StreamDecoratorTrait implements StreamInterface
{
function seek($offset,$whence=SEEK_SET){
throw new\RuntimeException('Cannot seek a NoSeekStream');
}
function isSeekable(){
return false;
}
}
namespace RingCentral\Psr7;
use Psr\Http\Message\StreamInterface;
class PumpStream implements StreamInterface
{
private$source;
private$size;
private$tellPos=0;
private$metadata;
private$buffer;
function __construct($source,array$options=array()){
$this->source=$source;
$this->size=isset($options['size'])?$options['size']:null;
$this->metadata=isset($options['metadata'])?$options['metadata']:array();
$this->buffer=new BufferStream;
}
function __toString(){
try{
return copy_to_string($this);
}catch(\Exception$e){
return'';
}
}
function close(){
$this->detach();
}
function detach(){
$this->tellPos=false;
$this->source=null;
}
function getSize(){
return$this->size;
}
function tell(){
return$this->tellPos;
}
function eof(){
return!$this->source;
}
function isSeekable(){
return false;
}
function rewind(){
$this->seek(0);
}
function seek($offset,$whence=SEEK_SET){
throw new\RuntimeException('Cannot seek a PumpStream');
}
function isWritable(){
return false;
}
function write($string){
throw new\RuntimeException('Cannot write to a PumpStream');
}
function isReadable(){
return true;
}
function read($length){
$data=$this->buffer->read($length);
$readLen=strlen($data);
$this->tellPos+=$readLen;
$remaining=$length-$readLen;
if($remaining){
$this->pump($remaining);
$data.=$this->buffer->read($remaining);
$this->tellPos+=strlen($data)-$readLen;
}
return$data;
}
function getContents(){
$result='';
while(!$this->eof()){
$result.=$this->read(1000000);
}
return$result;
}
function getMetadata($key=null){
if(!$key){
return$this->metadata;
}
return isset($this->metadata[$key])?$this->metadata[$key]:null;
}
private function pump($length){
if($this->source){
do{
$data=call_user_func($this->source,$length);
if($data===false||$data===null){
$this->source=null;
return;
}
$this->buffer->write($data);
$length-=strlen($data);
}while($length>0);
}
}
}
namespace RingCentral\Psr7;
use Psr\Http\Message\ServerRequestInterface;
use RingCentral\Psr7\Request;
class ServerRequest extends Request implements ServerRequestInterface
{
private$attributes=array();
private$serverParams=array();
private$fileParams=array();
private$cookies=array();
private$queryParams=array();
private$parsedBody=null;
function __construct($method,$uri,array$headers=array(),$body=null,$protocolVersion='1.1',$serverParams=array()){
parent::__construct($method,$uri,$headers,$body,$protocolVersion);
$this->serverParams=$serverParams;
}
function getServerParams(){
return$this->serverParams;
}
function getCookieParams(){
return$this->cookies;
}
function withCookieParams(array$cookies){
$new=clone$this;
$new->cookies=$cookies;
return$new;
}
function getQueryParams(){
return$this->queryParams;
}
function withQueryParams(array$query){
$new=clone$this;
$new->queryParams=$query;
return$new;
}
function getUploadedFiles(){
return$this->fileParams;
}
function withUploadedFiles(array$uploadedFiles){
$new=clone$this;
$new->fileParams=$uploadedFiles;
return$new;
}
function getParsedBody(){
return$this->parsedBody;
}
function withParsedBody($data){
$new=clone$this;
$new->parsedBody=$data;
return$new;
}
function getAttributes(){
return$this->attributes;
}
function getAttribute($name,$default=null){
if(!key_exists($name,$this->attributes)){
return$default;
}
return$this->attributes[$name];
}
function withAttribute($name,$value){
$new=clone$this;
$new->attributes[$name]=$value;
return$new;
}
function withoutAttribute($name){
$new=clone$this;
unset($new->attributes[$name]);
return$new;
}
}
namespace RingCentral\Psr7;
use Psr\Http\Message\StreamInterface;
class Stream implements StreamInterface
{
private$stream;
private$size;
private$seekable;
private$readable;
private$writable;
private$uri;
private$customMetadata;
private static$readWriteHash=array('read'=>array('r'=>true,'w+'=>true,'r+'=>true,'x+'=>true,'c+'=>true,'rb'=>true,'w+b'=>true,'r+b'=>true,'x+b'=>true,'c+b'=>true,'rt'=>true,'w+t'=>true,'r+t'=>true,'x+t'=>true,'c+t'=>true,'a+'=>true
),'write'=>array('w'=>true,'w+'=>true,'rw'=>true,'r+'=>true,'x+'=>true,'c+'=>true,'wb'=>true,'w+b'=>true,'r+b'=>true,'x+b'=>true,'c+b'=>true,'w+t'=>true,'r+t'=>true,'x+t'=>true,'c+t'=>true,'a'=>true,'a+'=>true
));
function __construct($stream,$options=array()){
if(!is_resource($stream)){
throw new\InvalidArgumentException('Stream must be a resource');
}
if(isset($options['size'])){
$this->size=$options['size'];
}
$this->customMetadata=isset($options['metadata'])?$options['metadata']:array();
$this->stream=$stream;
$meta=stream_get_meta_data($this->stream);
$this->seekable=$meta['seekable'];
$this->readable=isset(self::$readWriteHash['read'][$meta['mode']]);
$this->writable=isset(self::$readWriteHash['write'][$meta['mode']]);
$this->uri=$this->getMetadata('uri');
}
function __get($name){
if($name=='stream'){
throw new\RuntimeException('The stream is detached');
}
throw new\BadMethodCallException('No value for '.$name);
}
function __destruct(){
$this->close();
}
function __toString(){
try{
$this->seek(0);
return(string)stream_get_contents($this->stream);
}catch(\Exception$e){
return'';
}
}
function getContents(){
$contents=stream_get_contents($this->stream);
if($contents===false){
throw new\RuntimeException('Unable to read stream contents');
}
return$contents;
}
function close(){
if(isset($this->stream)){
if(is_resource($this->stream)){
fclose($this->stream);
}
$this->detach();
}
}
function detach(){
if(!isset($this->stream)){
return;
}
$result=$this->stream;
unset($this->stream);
$this->size=$this->uri=null;
$this->readable=$this->writable=$this->seekable=false;
return$result;
}
function getSize(){
if($this->size!==null){
return$this->size;
}
if(!isset($this->stream)){
return;
}
if($this->uri){
clearstatcache(true,$this->uri);
}
$stats=fstat($this->stream);
if(isset($stats['size'])){
$this->size=$stats['size'];
return$this->size;
}
return;
}
function isReadable(){
return$this->readable;
}
function isWritable(){
return$this->writable;
}
function isSeekable(){
return$this->seekable;
}
function eof(){
return!$this->stream||feof($this->stream);
}
function tell(){
$result=ftell($this->stream);
if($result===false){
throw new\RuntimeException('Unable to determine stream position');
}
return$result;
}
function rewind(){
$this->seek(0);
}
function seek($offset,$whence=SEEK_SET){
if(!$this->seekable){
throw new\RuntimeException('Stream is not seekable');
}elseif(fseek($this->stream,$offset,$whence)===-1){
throw new\RuntimeException('Unable to seek to stream position '.$offset.' with whence '.var_export($whence,true));
}
}
function read($length){
if(!$this->readable){
throw new\RuntimeException('Cannot read from non-readable stream');
}
return fread($this->stream,$length);
}
function write($string){
if(!$this->writable){
throw new\RuntimeException('Cannot write to a non-writable stream');
}
$this->size=null;
$result=fputs($this->stream,$string);
if($result===false){
throw new\RuntimeException('Unable to write to stream');
}
return$result;
}
function getMetadata($key=null){
if(!isset($this->stream)){
return$key?null:array();
}elseif(!$key){
return$this->customMetadata+stream_get_meta_data($this->stream);
}elseif(isset($this->customMetadata[$key])){
return$this->customMetadata[$key];
}
$meta=stream_get_meta_data($this->stream);
return isset($meta[$key])?$meta[$key]:null;
}
}
namespace RingCentral\Psr7;
use Psr\Http\Message\StreamInterface;
class StreamWrapper
{
var$context;
private$stream;
private$mode;
static function getResource(StreamInterface$stream){
self::register();
if($stream->isReadable()){
$mode=$stream->isWritable()?'r+':'r';
}elseif($stream->isWritable()){
$mode='w';
}else{
throw new\InvalidArgumentException('The stream must be readable, '.'writable, or both.');
}
return fopen('guzzle://stream',$mode,null,stream_context_create(array('guzzle'=>array('stream'=>$stream))));
}
static function register(){
if(!in_array('guzzle',stream_get_wrappers())){
stream_wrapper_register('guzzle',__CLASS__);
}
}
function stream_open($path,$mode,$options,&$opened_path){
$options=stream_context_get_options($this->context);
if(!isset($options['guzzle']['stream'])){
return false;
}
$this->mode=$mode;
$this->stream=$options['guzzle']['stream'];
return true;
}
function stream_read($count){
return$this->stream->read($count);
}
function stream_write($data){
return(int)$this->stream->write($data);
}
function stream_tell(){
return$this->stream->tell();
}
function stream_eof(){
return$this->stream->eof();
}
function stream_seek($offset,$whence){
$this->stream->seek($offset,$whence);
return true;
}
function stream_stat(){
static$modeMap=array('r'=>33060,'r+'=>33206,'w'=>33188
);
return array('dev'=>0,'ino'=>0,'mode'=>$modeMap[$this->mode],'nlink'=>0,'uid'=>0,'gid'=>0,'rdev'=>0,'size'=>$this->stream->getSize()?:0,'atime'=>0,'mtime'=>0,'ctime'=>0,'blksize'=>0,'blocks'=>0
);
}
}
namespace RingCentral\Psr7;
use Psr\Http\Message\UriInterface;
class Uri implements UriInterface
{
private static$schemes=array('http'=>80,'https'=>443,);
private static$charUnreserved='a-zA-Z0-9_\-\.~';
private static$charSubDelims='!\$&\'\(\)\*\+,;=';
private static$replaceQuery=array('='=>'%3D','&'=>'%26');
private$scheme='';
private$userInfo='';
private$host='';
private$port;
private$path='';
private$query='';
private$fragment='';
function __construct($uri=''){
if($uri!=null){
$parts=parse_url($uri);
if($parts===false){
throw new\InvalidArgumentException("Unable to parse URI: $uri");
}
$this->applyParts($parts);
}
}
function __toString(){
return self::createUriString($this->scheme,$this->getAuthority(),$this->getPath(),$this->query,$this->fragment
);
}
static function removeDotSegments($path){
static$noopPaths=array(''=>true,'/'=>true,'*'=>true);
static$ignoreSegments=array('.'=>true,'..'=>true);
if(isset($noopPaths[$path])){
return$path;
}
$results=array();
$segments=explode('/',$path);
foreach($segments as$segment){
if($segment=='..'){
array_pop($results);
}elseif(!isset($ignoreSegments[$segment])){
$results[]=$segment;
}
}
$newPath=join('/',$results);
if(substr($path,0,1)==='/'&&
substr($newPath,0,1)!=='/'){
$newPath='/'.$newPath;
}
if($newPath!='/'&&isset($ignoreSegments[end($segments)])){
$newPath.='/';
}
return$newPath;
}
static function resolve(UriInterface$base,$rel){
if($rel===null||$rel===''){
return$base;
}
if(!($rel instanceof UriInterface)){
$rel=new self($rel);
}
if($rel->getScheme()){
return$rel->withPath(static::removeDotSegments($rel->getPath()));
}
$relParts=array('scheme'=>$rel->getScheme(),'authority'=>$rel->getAuthority(),'path'=>$rel->getPath(),'query'=>$rel->getQuery(),'fragment'=>$rel->getFragment());
$parts=array('scheme'=>$base->getScheme(),'authority'=>$base->getAuthority(),'path'=>$base->getPath(),'query'=>$base->getQuery(),'fragment'=>$base->getFragment());
if(!empty($relParts['authority'])){
$parts['authority']=$relParts['authority'];
$parts['path']=self::removeDotSegments($relParts['path']);
$parts['query']=$relParts['query'];
$parts['fragment']=$relParts['fragment'];
}elseif(!empty($relParts['path'])){
if(substr($relParts['path'],0,1)=='/'){
$parts['path']=self::removeDotSegments($relParts['path']);
$parts['query']=$relParts['query'];
$parts['fragment']=$relParts['fragment'];
}else{
if(!empty($parts['authority'])&&empty($parts['path'])){
$mergedPath='/';
}else{
$mergedPath=substr($parts['path'],0,strrpos($parts['path'],'/')+1);
}
$parts['path']=self::removeDotSegments($mergedPath.$relParts['path']);
$parts['query']=$relParts['query'];
$parts['fragment']=$relParts['fragment'];
}
}elseif(!empty($relParts['query'])){
$parts['query']=$relParts['query'];
}elseif($relParts['fragment']!=null){
$parts['fragment']=$relParts['fragment'];
}
return new self(static::createUriString($parts['scheme'],$parts['authority'],$parts['path'],$parts['query'],$parts['fragment']));
}
static function withoutQueryValue(UriInterface$uri,$key){
$current=$uri->getQuery();
if(!$current){
return$uri;
}
$result=array();
foreach(explode('&',$current)as$part){
$subParts=explode('=',$part);
if($subParts[0]!==$key){
$result[]=$part;
};
}
return$uri->withQuery(join('&',$result));
}
static function withQueryValue(UriInterface$uri,$key,$value){
$current=$uri->getQuery();
$key=strtr($key,self::$replaceQuery);
if(!$current){
$result=array();
}else{
$result=array();
foreach(explode('&',$current)as$part){
$subParts=explode('=',$part);
if($subParts[0]!==$key){
$result[]=$part;
};
}
}
if($value!==null){
$result[]=$key.'='.strtr($value,self::$replaceQuery);
}else{
$result[]=$key;
}
return$uri->withQuery(join('&',$result));
}
static function fromParts(array$parts){
$uri=new self;
$uri->applyParts($parts);
return$uri;
}
function getScheme(){
return$this->scheme;
}
function getAuthority(){
if(empty($this->host)){
return'';
}
$authority=$this->host;
if(!empty($this->userInfo)){
$authority=$this->userInfo.'@'.$authority;
}
if($this->isNonStandardPort($this->scheme,$this->host,$this->port)){
$authority.=':'.$this->port;
}
return$authority;
}
function getUserInfo(){
return$this->userInfo;
}
function getHost(){
return$this->host;
}
function getPort(){
return$this->port;
}
function getPath(){
return$this->path==null?'':$this->path;
}
function getQuery(){
return$this->query;
}
function getFragment(){
return$this->fragment;
}
function withScheme($scheme){
$scheme=$this->filterScheme($scheme);
if($this->scheme===$scheme){
return$this;
}
$new=clone$this;
$new->scheme=$scheme;
$new->port=$new->filterPort($new->scheme,$new->host,$new->port);
return$new;
}
function withUserInfo($user,$password=null){
$info=$user;
if($password){
$info.=':'.$password;
}
if($this->userInfo===$info){
return$this;
}
$new=clone$this;
$new->userInfo=$info;
return$new;
}
function withHost($host){
if($this->host===$host){
return$this;
}
$new=clone$this;
$new->host=$host;
return$new;
}
function withPort($port){
$port=$this->filterPort($this->scheme,$this->host,$port);
if($this->port===$port){
return$this;
}
$new=clone$this;
$new->port=$port;
return$new;
}
function withPath($path){
if(!is_string($path)){
throw new\InvalidArgumentException('Invalid path provided; must be a string');
}
$path=$this->filterPath($path);
if($this->path===$path){
return$this;
}
$new=clone$this;
$new->path=$path;
return$new;
}
function withQuery($query){
if(!is_string($query)&&!method_exists($query,'__toString')){
throw new\InvalidArgumentException('Query string must be a string');
}
$query=(string)$query;
if(substr($query,0,1)==='?'){
$query=substr($query,1);
}
$query=$this->filterQueryAndFragment($query);
if($this->query===$query){
return$this;
}
$new=clone$this;
$new->query=$query;
return$new;
}
function withFragment($fragment){
if(substr($fragment,0,1)==='#'){
$fragment=substr($fragment,1);
}
$fragment=$this->filterQueryAndFragment($fragment);
if($this->fragment===$fragment){
return$this;
}
$new=clone$this;
$new->fragment=$fragment;
return$new;
}
private function applyParts(array$parts){
$this->scheme=isset($parts['scheme'])?$this->filterScheme($parts['scheme']):'';
$this->userInfo=isset($parts['user'])?$parts['user']:'';
$this->host=isset($parts['host'])?$parts['host']:'';
$this->port=!empty($parts['port'])?$this->filterPort($this->scheme,$this->host,$parts['port']):null;
$this->path=isset($parts['path'])?$this->filterPath($parts['path']):'';
$this->query=isset($parts['query'])?$this->filterQueryAndFragment($parts['query']):'';
$this->fragment=isset($parts['fragment'])?$this->filterQueryAndFragment($parts['fragment']):'';
if(isset($parts['pass'])){
$this->userInfo.=':'.$parts['pass'];
}
}
private static function createUriString($scheme,$authority,$path,$query,$fragment){
$uri='';
if(!empty($scheme)){
$uri.=$scheme.'://';
}
if(!empty($authority)){
$uri.=$authority;
}
if($path!=null){
if($uri&&substr($path,0,1)!=='/'){
$uri.='/';
}
$uri.=$path;
}
if($query!=null){
$uri.='?'.$query;
}
if($fragment!=null){
$uri.='#'.$fragment;
}
return$uri;
}
private static function isNonStandardPort($scheme,$host,$port){
if(!$scheme&&$port){
return true;
}
if(!$host||!$port){
return false;
}
return!isset(static::$schemes[$scheme])||$port!==static::$schemes[$scheme];
}
private function filterScheme($scheme){
$scheme=strtolower($scheme);
$scheme=rtrim($scheme,':/');
return$scheme;
}
private function filterPort($scheme,$host,$port){
if(null!==$port){
$port=(int)$port;
if(1>$port||65535<$port){
throw new\InvalidArgumentException(sprintf('Invalid port: %d. Must be between 1 and 65535',$port));
}
}
return$this->isNonStandardPort($scheme,$host,$port)?$port:null;
}
private function filterPath($path){
return preg_replace_callback('/(?:[^'.self::$charUnreserved.self::$charSubDelims.':@\/%]+|%(?![A-Fa-f0-9]{2}))/',array($this,'rawurlencodeMatchZero'),$path
);
}
private function filterQueryAndFragment($str){
return preg_replace_callback('/(?:[^'.self::$charUnreserved.self::$charSubDelims.'%:@\/\?]+|%(?![A-Fa-f0-9]{2}))/',array($this,'rawurlencodeMatchZero'),$str
);
}
private function rawurlencodeMatchZero(array$match){
return rawurlencode($match[0]);
}
}
namespace LeProxy\LeProxy;
use Clue\Commander\Router;
use Clue\Commander\NoRouteFoundException;
use Clue\Commander\Tokens\Tokenizer;
use React\EventLoop\Factory;
use React\Dns\Config\HostsFile;
if(PHP_VERSION_ID<50400||PHP_SAPI!=='cli'){
echo'LeProxy HTTP/SOCKS proxy requires running '.(PHP_SAPI!=='cli'?('via command line (not '.PHP_SAPI.')'):('on PHP 5.4+ (is '.PHP_VERSION.')')).PHP_EOL;
die(1);
}
const VERSION="0.2.2";
$tokenizer=new Tokenizer;
$tokenizer->addFilter('block',function(&$value){
$value=ConnectorFactory::coerceBlockUri($value);
return true;
});
$tokenizer->addFilter('proxy',function(&$value){
$value=ConnectorFactory::coerceProxyUri($value);
return true;
});
$tokenizer->addFilter('hosts',function(&$value){
$value=HostsFile::loadFromPathBlocking($value)->getHostsForIp('0.0.0.0');
return true;
});
$commander=new Router($tokenizer);
$commander->add('--version',function(){
die('LeProxy release version '.VERSION.PHP_EOL);
});
$commander->add('-h | --help',function(){
die('LeProxy HTTP/SOCKS proxy

Usage:
    $ php leproxy.php [<listenAddress>] [--allow-unprotected] [--block=<destination>...] [--block-hosts=<path>...] [--proxy=<upstreamProxy>...] [--no-log]
    $ php leproxy.php --version
    $ php leproxy.php --help

Arguments:
    <listenAddress>
        The socket address to listen on.
        The address consists of a full URI which may contain a username and
        password, host and port (or Unix domain socket path).
        By default, LeProxy will listen on the public address 0.0.0.0:8080.
        LeProxy will report an error if it fails to listen on the given address,
        you may try another address or use port `0` to pick a random free port.
        If this address does not contain a username and password, LeProxy will
        run in protected mode and only forward requests from the local host,
        see also `--allow-unprotected`.

    --allow-unprotected
        If no username and password has been given, then LeProxy runs in
        protected mode by default, so that it only forwards requests from the
        local host and can not be abused as an open proxy.
        If you have ensured only legit users can access your system, you can
        pass the `--allow-unprotected` flag to forward requests from all hosts.
        This option should be used with care, you have been warned.

    --block=<destination>
        Blocks forwarding connections to the given destination address.
        Any number of destination addresses can be given.
        Each destination address can be in the form `host` or `host:port` and
        `host` may contain the `*` wildcard to match anything.
        Subdomains for each host will automatically be blocked.

    --block-hosts=<path>
        Loads the hosts file from the given file path and blocks all of the
        hostnames (and subdomains) that match the IP `0.0.0.0`.
        Any number of hosts files can be given, all hosts will be blocked.

    --proxy=<upstreamProxy>
        An upstream proxy server where each connection request will be
        forwarded to (proxy chaining).
        Any number of upstream proxies can be given.
        Each address consists of full URI which may contain a scheme, username
        and password, host and port (or Unix domain socket path). Default scheme
        is `http://`, default port is `8080` for all schemes.

    --no-log
        By default, LeProxy logs all connection attempts to STDOUT for
        debugging purposes. This can be avoided by passing this argument.

    --version
        Prints the current version of LeProxy and exits.

    --help, -h
        Shows this help and exits.

Examples:
    $ php leproxy.php
        Runs LeProxy on public default address 0.0.0.0:8080 (protected mode)

    $ php leproy.php 127.0.0.1:1080
        Runs LeProxy on custom address 127.0.0.1:1080 (protected mode, local only)

    $ php leproxy.php user:pass@0.0.0.0:8080
        Runs LeProxy on public default addresses and require authentication

    $ php leproxy.php --block=youtube.com --block=*:80
        Runs LeProxy on default address and blocks access to youtube.com and
        port 80 on all hosts (standard plaintext HTTP port).

    $ php leproxy.php --proxy=http://user:pass@127.1.1.1:8080
        Runs LeProxy so that all connection requests will be forwarded through
        an upstream proxy server that requires authentication.
');
});
$commander->add('[--allow-unprotected] [--block=<block:block>...] [--block-hosts=<file:hosts>...] [--proxy=<proxy:proxy>...] [--no-log] [<listen>]',function($args){
$args['listen']=ConnectorFactory::coerceListenUri(isset($args['listen'])?$args['listen']:'');
$args['allow-unprotected']=isset($args['allow-unprotected']);
if($args['allow-unprotected']&&strpos($args['listen'],'@')!==false){
throw new\InvalidArgumentException('Unprotected mode can not be used with authentication required');
}
if(isset($args['block-hosts'])){
if(!isset($args['block'])){
$args['block']=array();
}
foreach($args['block-hosts']as$hosts){
$args['block']+=$hosts;
}
}
if(isset($args['block'])){
$args['block']=ConnectorFactory::filterRootDomains($args['block']);
}
return$args;
});
try{
$args=$commander->handleArgv();
}catch(\Exception$e){
$message='';
if(!$e instanceof NoRouteFoundException){
$message=' ('.$e->getMessage().')';
}
fputs(STDERR,'Usage Error: Invalid command arguments given, see --help'.$message.PHP_EOL);
die(64);
}
$loop=Factory::create();
$connector=ConnectorFactory::createConnectorChain(isset($args['proxy'])?$args['proxy']:array(),$loop);
if(isset($args['block'])){
$connector=ConnectorFactory::createBlockingConnector($args['block'],$connector);
}
if(!isset($args['no-log'])){
$connector=new LoggingConnector($connector,new Logger);
}
$proxy=new LeProxyServer($loop,$connector);
try{
$socket=$proxy->listen($args['listen'],$args['allow-unprotected']);
}catch(\RuntimeException$e){
fputs(STDERR,'Program error: Unable to start listening, maybe try another port? ('.$e->getMessage().')'.PHP_EOL);
die(71);
}
$addr=str_replace(array('tcp://','unix://'),array('http://','http+unix://'),$socket->getAddress());
echo'LeProxy HTTP/SOCKS proxy now listening on '.$addr.' (';
if(strpos($args['listen'],'@')!==false){
echo'authentication required';
}elseif($args['allow-unprotected']){
echo'unprotected mode, open proxy';
}else{
echo'protected mode, local access only';
}
echo')'.PHP_EOL;
if(isset($args['proxy'])){
echo'Forwarding via: '.join(' -> ',$args['proxy']).PHP_EOL;
}
if(isset($args['block'])){
echo'Blocking a total of '.count($args['block']).' destination(s)'.PHP_EOL;
}
$loop->run();
