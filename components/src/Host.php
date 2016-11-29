<?php
/**
 * League.Uri (http://uri.thephpleague.com)
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @copyright  2016 Ignace Nyamagana Butera
 * @license    https://github.com/thephpleague/uri-components/blob/master/LICENSE (MIT License)
 * @version    1.0.0
 * @link       https://github.com/thephpleague/uri-components
 */
namespace League\Uri\Components;

use League\Uri\Components\Traits\HostInfo;
use League\Uri\Interfaces\CollectionComponent;
use Traversable;

/**
 * Value object representing a URI Host component.
 *
 * Instances of this interface are considered immutable; all methods that
 * might change state MUST be implemented such that they retain the internal
 * state of the current instance and return an instance that contains the
 * changed state.
 *
 * @package    League\Uri
 * @subpackage League\Uri\Components
 * @author     Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since      1.0.0
 * @see        https://tools.ietf.org/html/rfc3986#section-3.2.2
 */
class Host extends HierarchicalComponent implements CollectionComponent
{
    use HostInfo;

    /**
     * Tell whether the Host is an IPv4
     *
     * @var bool
     */
    protected $host_as_ipv4 = false;

    /**
     * Tell whether the Host is an IPv6
     *
     * @var bool
     */
    protected $host_as_ipv6 = false;

    /**
     * Tell whether the Host contains a ZoneID
     *
     * @var bool
     */
    protected $has_zone_identifier = false;

    /**
     * HierarchicalComponent delimiter
     *
     * @var string
     */
    protected static $separator = '.';

    /**
     * Host literal representation
     *
     * @var string
     */
    protected $host;

    /**
     * return a new instance from an array or a traversable object
     *
     * @param Traversable|string[] $data The segments list
     * @param int                  $type one of the constant IS_ABSOLUTE or IS_RELATIVE
     *
     * @throws Exception If $type is not a recognized constant
     *
     * @return static
     */
    public static function createFromLabels($data, $type = self::IS_RELATIVE)
    {
        static $type_list = [self::IS_ABSOLUTE => 1, self::IS_RELATIVE => 1];

        $data = static::validateIterator($data);
        if (!isset($type_list[$type])) {
            throw Exception::fromInvalidFlag($type);
        }

        if ([] === $data) {
            return new static();
        }

        if ([''] === $data) {
            return new static('');
        }

        return new static(static::format($data, $type));
    }

    /**
     * Return a formatted host string
     *
     * @param string[] $data The segments list
     * @param int      $type
     *
     * @return string
     */
    protected static function format(array $data, $type)
    {
        $hostname = implode(static::$separator, array_reverse($data));
        if (self::IS_ABSOLUTE === $type) {
            return $hostname.static::$separator;
        }

        return $hostname;
    }

    /**
     * Return a host from an IP address
     *
     * @param string $ip
     *
     * @throws Exception If the IP is invalid or unrecognized
     *
     * @return static
     */
    public static function createFromIp($ip)
    {
        $ip = static::validateString($ip);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return new static($ip);
        }

        if (false !== strpos($ip, '%')) {
            $parts = explode('%', rawurldecode($ip));
            $ip = array_shift($parts).'%25'.rawurlencode((string) array_shift($parts));

            return new static('['.$ip.']');
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return new static('['.$ip.']');
        }

        throw new Exception(sprintf('Please verify the submitted IP: %s', $ip));
    }

    /**
     * New instance
     *
     * @param null|string $host
     */
    public function __construct($host = null)
    {
        $this->data = $this->validate($host);
    }

    /**
     * validate the submitted data
     *
     * @param string $host
     *
     * @throws Exception If the host is invalid
     *
     * @return array
     */
    protected function validate($host)
    {
        if (null === $host) {
            return [];
        }

        $host = $this->validateString($host);
        if ('' === $host) {
            return [''];
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->host_as_ipv4 = true;

            return [$host];
        }

        if ($this->isValidHostnameIpv6($host)) {
            $this->host_as_ipv6 = true;
            $this->has_zone_identifier = false !== strpos($host, '%');

            return [$host];
        }

        if ($this->isValidHostname($host)) {

            return array_reverse(array_map(
                'idn_to_utf8',
                explode('.', strtolower($this->setIsAbsolute($host)))
            ));
        }

        throw new Exception(sprintf('The submitted host `%s` is invalid', $host));
    }

    /**
     * Return a new instance when needed
     *
     * @param array $data
     *
     * @return static
     */
    protected function newCollectionInstance(array $data)
    {
        return $this->createFromLabels($data, $this->isAbsolute);
    }

    /**
     * Returns whether or not the host is an IP address
     *
     * @return bool
     */
    public function isIp()
    {
        return $this->host_as_ipv4 || $this->host_as_ipv6;
    }

    /**
     * Returns whether or not the host is an IPv4 address
     *
     * @return bool
     */
    public function isIpv4()
    {
        return $this->host_as_ipv4;
    }

    /**
     * Returns whether or not the host is an IPv6 address
     *
     * @return bool
     */
    public function isIpv6()
    {
        return $this->host_as_ipv6;
    }

    /**
     * Returns whether or not the host has a ZoneIdentifier
     *
     * @return bool
     *
     * @see http://tools.ietf.org/html/rfc6874#section-4
     */
    public function hasZoneIdentifier()
    {
        return $this->has_zone_identifier;
    }

    /**
     * Returns an array representation of the Host
     *
     * @return array
     */
    public function getLabels()
    {
        return $this->data;
    }

    /**
     * Retrieves a single host label.
     *
     * Retrieves a single host label. If the label offset has not been set,
     * returns the default value provided.
     *
     * @param string $offset  the label offset
     * @param mixed  $default Default value to return if the offset does not exist.
     *
     * @return mixed
     */
    public function getLabel($offset, $default = null)
    {
        if (isset($this->data[$offset])) {
            return $this->data[$offset];
        }

        return $default;
    }

    /**
     * Returns the instance content encoded in RFC3986 or RFC3987.
     *
     * If the instance is defined, the value returned MUST be percent-encoded,
     * but MUST NOT double-encode any characters depending on the encoding type selected.
     *
     * To determine what characters to encode, please refer to RFC 3986, Sections 2 and 3.
     * or RFC 3987 Section 3.
     *
     * By default the content is encoded according to RFC3986
     *
     * If the instance is not defined null is returned
     *
     * @param string $enc_type
     *
     * @return string|null
     */
    public function getContent($enc_type = self::RFC3986)
    {
        if (!in_array($enc_type, [self::RFC3986, self::RFC3987])) {
            throw new Exception('Unsupported or Unknown Encoding');
        }

        if ([] === $this->data) {
            return null;
        }

        if ($this->isIp()) {
            return $this->data[0];
        }

        if ($enc_type == self::RFC3987) {
            return $this->format($this->data, $this->isAbsolute);
        }

        return $this->format(array_map('idn_to_ascii', $this->data), $this->isAbsolute);
    }

    /**
     * Retrieve the IP component If the Host is an IP adress.
     *
     * If the host is a domain name this method will return null
     *
     * @return string
     */
    public function getIp()
    {
        if ($this->host_as_ipv4) {
            return $this->data[0];
        }

        if (!$this->host_as_ipv6) {
            return null;
        }

        $ip = substr($this->data[0], 1, -1);
        if (false === ($pos = strpos($ip, '%'))) {
            return $ip;
        }

        return substr($ip, 0, $pos).'%'.rawurldecode(substr($ip, $pos + 3));
    }

    /**
     * @inheritdoc
     */
    public function __debugInfo()
    {
        return ['host' => $this->getContent()];
    }

    /**
     * @inheritdoc
     */
    public static function __set_state(array $properties)
    {
        $host = static::createFromLabels($properties['data'], $properties['isAbsolute']);
        $host->hostnameInfoLoaded = $properties['hostnameInfoLoaded'];
        $host->hostnameInfo = $properties['hostnameInfo'];

        return $host;
    }

    /**
     * Return an host without its zone identifier according to RFC6874
     *
     * This method MUST retain the state of the current instance, and return
     * an instance without the host zone identifier according to RFC6874
     *
     * @see http://tools.ietf.org/html/rfc6874#section-4
     *
     * @return static
     */
    public function withoutZoneIdentifier()
    {
        if (!$this->has_zone_identifier) {
            return $this;
        }

        return $this->withContent(substr($this->data[0], 0, strpos($this->data[0], '%')).']');
    }

    /**
     * set the FQDN property
     *
     * @param string $str
     *
     * @return string
     */
    protected function setIsAbsolute($str)
    {
        $this->isAbsolute = self::IS_RELATIVE;
        if ('.' === mb_substr($str, -1, 1, 'UTF-8')) {
            $this->isAbsolute = self::IS_ABSOLUTE;
            $str = mb_substr($str, 0, -1, 'UTF-8');
        }

        return $str;
    }

    /**
     * @inheritdoc
     */
    public function prepend($component)
    {
        return $this->createFromLabels(
            $this->withContent($component),
            $this->isAbsolute
        )->append($this->__toString());
    }

    /**
     * @inheritdoc
     */
    public function append($component)
    {
        return $this->createFromLabels(array_merge(
            iterator_to_array($this->withContent($component)),
            $this->getLabels()
        ), $this->isAbsolute);
    }
}
