<?php
namespace Zer0\Cache\Traits;

trait Hash
{

    /**
     * @param $value
     * @return string
     */
    public function hash($value): string
    {
        return \Zer0\Helpers\Str::base64UrlEncode(
            hash(
                $this->config->hash_algo ?? 'sha3-224',
                igbinary_serialize($value),
                true
            )
        );
    }
}
