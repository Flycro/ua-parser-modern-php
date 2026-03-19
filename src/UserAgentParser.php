<?php

namespace UaParserModern;

/**
 * PHP port of ua-parser-modern (https://github.com/antfu-collective/ua-parser-modern).
 *
 * Original work:
 *   Copyright (c) 2026 Anthony Fu <anthonyfu117@hotmail.com>
 *   Copyright (c) 2024 Matteo Collina <hello@matteocollina.com>
 *   Copyright (c) 2012-2023 Faisal Salman <f@faisalman.com>
 *
 * Every regex is ported in exact order from the JS source to ensure identical matching behavior.
 */
class UserAgentParser
{
    private const UA_MAX_LENGTH = 500;

    /** @var array<string, list<mixed>> */
    private static array $regexes = [];

    /** @var array<string, string> */
    private static array $oldSafariMap = [
        '1.0' => '/8',
        '1.2' => '/1',
        '1.3' => '/3',
        '2.0' => '/412',
        '2.0.2' => '/416',
        '2.0.3' => '/417',
        '2.0.4' => '/419',
        '?' => '/',
    ];

    /** @var array<string, string|array<int, string>> */
    private static array $windowsVersionMap = [
        'ME' => '4.90',
        'NT 3.11' => 'NT3.51',
        'NT 4.0' => 'NT4.0',
        '2000' => 'NT 5.0',
        'XP' => ['NT 5.1', 'NT 5.2'],
        'Vista' => 'NT 6.0',
        '7' => 'NT 6.1',
        '8' => 'NT 6.2',
        '8.1' => 'NT 6.3',
        '10' => ['NT 6.4', 'NT 10.0'],
        'RT' => 'ARM',
    ];

    public static function parse(string $ua): ParseResult
    {
        $ua = self::normalizeUA($ua);

        return new ParseResult(
            ua: $ua,
            browser: self::parseBrowser($ua),
            device: self::parseDevice($ua),
            engine: self::parseEngine($ua),
            os: self::parseOS($ua),
            cpu: self::parseCPU($ua),
        );
    }

    public static function parseBrowser(string $ua): BrowserResult
    {
        $ua = self::normalizeUA($ua);
        $result = self::rgxMapper($ua, self::getRegexes()['browser']);

        return new BrowserResult(
            name: $result['name'] ?? null,
            version: $result['version'] ?? null,
            major: isset($result['version']) ? self::majorize($result['version']) : null,
        );
    }

    public static function parseDevice(string $ua): DeviceResult
    {
        $ua = self::normalizeUA($ua);
        $result = self::rgxMapper($ua, self::getRegexes()['device']);

        return new DeviceResult(
            model: $result['model'] ?? null,
            type: $result['type'] ?? null,
            vendor: $result['vendor'] ?? null,
        );
    }

    public static function parseEngine(string $ua): EngineResult
    {
        $ua = self::normalizeUA($ua);
        $result = self::rgxMapper($ua, self::getRegexes()['engine']);

        return new EngineResult(
            name: $result['name'] ?? null,
            version: $result['version'] ?? null,
        );
    }

    public static function parseOS(string $ua): OSResult
    {
        $ua = self::normalizeUA($ua);
        $result = self::rgxMapper($ua, self::getRegexes()['os']);

        return new OSResult(
            name: $result['name'] ?? null,
            version: $result['version'] ?? null,
        );
    }

    public static function parseCPU(string $ua): CPUResult
    {
        $ua = self::normalizeUA($ua);
        $result = self::rgxMapper($ua, self::getRegexes()['cpu']);

        return new CPUResult(
            architecture: $result['architecture'] ?? null,
        );
    }

    // ─── helpers ───

    private static function normalizeUA(string $ua): string
    {
        return mb_strlen($ua) > self::UA_MAX_LENGTH
            ? ltrim(mb_substr($ua, 0, self::UA_MAX_LENGTH))
            : ltrim($ua);
    }

    private static function majorize(?string $version): ?string
    {
        if ($version === null) {
            return null;
        }
        $cleaned = preg_replace('/[^\d.]/', '', $version);
        $major = explode('.', $cleaned ?? '')[0];

        return $major !== '' ? $major : null;
    }

    /**
     * @param  list<mixed>  $arrays
     * @return array<string, ?string>
     */
    private static function rgxMapper(string $ua, array $arrays): array
    {
        $result = [];

        for ($i = 0, $len = count($arrays); $i < $len; $i += 2) {
            $regexGroup = $arrays[$i];
            $props = $arrays[$i + 1];

            foreach ($regexGroup as $regex) {
                if (! preg_match($regex, $ua, $matches)) {
                    continue;
                }

                foreach ($props as $p => $prop) {
                    $rawMatch = $matches[$p + 1] ?? null;

                    if (is_array($prop)) {
                        $match = $rawMatch;
                        $key = $prop[0];
                        $cnt = count($prop);

                        if ($cnt === 2) {
                            $val = $prop[1];
                            $result[$key] = $val instanceof \Closure ? $val($match) : $val;
                        } elseif ($cnt === 3) {
                            $a1 = $prop[1];
                            $a2 = $prop[2];
                            if ($a1 instanceof \Closure) {
                                $result[$key] = $match !== null ? $a1($match, $a2) : null;
                            } else {
                                $result[$key] = $match !== null ? preg_replace($a1, $a2, $match) : null;
                            }
                        } elseif ($cnt === 4) {
                            $result[$key] = $match !== null
                                ? ($prop[3])(preg_replace($prop[1], $prop[2], $match))
                                : null;
                        }
                    } else {
                        // Simple string props: empty → null (matches JS `match || undefined`)
                        $result[$prop] = $rawMatch ?: null;
                    }
                }

                return $result;
            }
        }

        return $result;
    }

    /** @param array<string, string|array<int, string>> $map */
    private static function strMapper(?string $str, array $map): ?string
    {
        if ($str === null || $str === '') {
            return null;
        }
        $lower = strtolower($str);
        foreach ($map as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    if (str_contains($lower, strtolower($v))) {
                        return $key === '?' ? null : (string) $key;
                    }
                }
            } elseif (str_contains($lower, strtolower($value))) {
                return $key === '?' ? null : (string) $key;
            }
        }

        return $str;
    }

    // ─── regex maps (exact order from JS source) ───

    /** @return array<string, list<mixed>> */
    private static function getRegexes(): array
    {
        if (self::$regexes !== []) {
            return self::$regexes;
        }

        $lowerize = static fn (?string $s): ?string => $s !== null ? strtolower($s) : null;
        $strMapperOldSafari = static fn (?string $m): ?string => self::strMapper($m, self::$oldSafariMap);
        $strMapperWinVer = static fn (?string $m): ?string => self::strMapper($m, self::$windowsVersionMap);

        // ═══════════════════════════════════════
        //  BROWSER
        // ═══════════════════════════════════════
        $browser = [
            // Chrome for Android/iOS
            ['/\b(?:crmo|crios)\/([\w.]+)/i'], ['version', ['name', 'Chrome']],
            // Microsoft Edge
            ['/edg(?:e|ios|a)?\/([\w.]+)/i'], ['version', ['name', 'Edge']],

            // Presto based
            ['/(opera mini)\/([-\w.]+)/i',
                '/(opera [mobileta]{3,6})\b.+version\/([-\w.]+)/i',
                '/(opera)(?:.+version\/|[\/\s]+)([\w.]+)/i'], ['name', 'version'],
            ['/opios[\/\s]+([\w.]+)/i'], ['version', ['name', 'Opera Mini']],
            ['/\bop(?:rg)?x\/([\w.]+)/i'], ['version', ['name', 'Opera GX']],
            ['/\bopr\/([\w.]+)/i'], ['version', ['name', 'Opera']],

            // Mixed
            ['/\bb[ai]*d(?:uhd|[ub]*[aekoprswx]{5,6})[\/\s]?([\w.]+)/i'], ['version', ['name', 'Baidu']],
            ['/(kindle)\/([\w.]+)/i',
                '/(lunascape|maxthon|netfront|jasmine|blazer)[\/\s]?([\w.]*)/i',
                '/(avant|iemobile|slim)\s?(?:browser)?[\/\s]?([\w.]*)/i',
                '/(?:ms|\()(ie)\s([\w.]+)/i',
                '/(flock|rockmelt|midori|epiphany|silk|skyfire|bolt|iron|vivaldi|iridium|phantomjs|bowser|quark|qupzilla|falkon|rekonq|puffin|brave|whale(?!.+naver)|qqbrowserlite|qq|duckduckgo)\/([-\w.]+)/i',
                '/(heytap|ovi)browser\/([\d.]+)/i',
                '/(weibo)__([\d.]+)/i'], ['name', 'version'],
            ['/\bddg\/([\w.]+)/i'], ['version', ['name', 'DuckDuckGo']],
            ['/(?:\buc?\s?browser|juc.+ucweb)[\/\s]?([\w.]+)/i'], ['version', ['name', 'UCBrowser']],
            ['/microm.+\bqbcore\/([\w.]+)/i',
                '/\bqbcore\/([\w.]+).+microm/i',
                '/micromessenger\/([\w.]+)/i'], ['version', ['name', 'WeChat']],
            ['/konqueror\/([\w.]+)/i'], ['version', ['name', 'Konqueror']],
            // IE11
            ['/trident.+rv[:\s]([\w.]{1,9})\b.+like gecko/i'], ['version', ['name', 'IE']],
            ['/ya(?:search)?browser\/([\w.]+)/i'], ['version', ['name', 'Yandex']],
            ['/slbrowser\/([\w.]+)/i'], ['version', ['name', 'Smart Lenovo Browser']],
            ['/(avast|avg)\/([\w.]+)/i'], [['name', '/(.+)/', '$1 Secure Browser'], 'version'],
            ['/\bfocus\/([\w.]+)/i'], ['version', ['name', 'Firefox Focus']],
            ['/\bopt\/([\w.]+)/i'], ['version', ['name', 'Opera Touch']],
            ['/coc_coc\w+\/([\w.]+)/i'], ['version', ['name', 'Coc Coc']],
            ['/dolfin\/([\w.]+)/i'], ['version', ['name', 'Dolphin']],
            ['/coast\/([\w.]+)/i'], ['version', ['name', 'Opera Coast']],
            ['/miuibrowser\/([\w.]+)/i'], ['version', ['name', 'MIUI Browser']],
            ['/fxios\/([-\w.]+)/i'], ['version', ['name', 'Firefox']],
            ['/\bqihu|(qi?ho{0,2}|360)browser/i'], [['name', '360 Browser']],
            ['/(oculus|sailfish|huawei|vivo)browser\/([\w.]+)/i'], [['name', '/(.+)/', '$1 Browser'], 'version'],
            ['/samsungbrowser\/([\w.]+)/i'], ['version', ['name', 'Samsung Internet']],
            ['/(comodo_dragon)\/([\w.]+)/i'], [['name', '/_/', ' '], 'version'],
            ['/metasr[\/\s]?([\d.]+)/i'], ['version', ['name', 'Sogou Explorer']],
            ['/(sogou)mo\w+\/([\d.]+)/i'], [['name', 'Sogou Mobile'], 'version'],
            ['/(electron)\/([\w.]+)\ssafari/i',
                '/(tesla)(?:\sqtcarbrowser|\/(20\d\d\.[-\w.]+))/i',
                '/m?(qqbrowser|2345Explorer)[\/\s]?([\w.]+)/i'], ['name', 'version'],
            ['/(lbbrowser)/i',
                '/\[(linkedin)app\]/i'], ['name'],

            // WebView
            ['/((?:fban\/fbios|fb_iab\/fb4a)(?!.+fbav)|;fbav\/([\w.]+);)/i'], [['name', 'Facebook'], 'version'],
            ['/(Klarna)\/([\w.]+)/i',
                '/(kakao(?:talk|story))[\/\s]([\w.]+)/i',
                '/(naver)\(.*?(\d+\.[\w.]+).*\)/i',
                '/safari\s(line)\/([\w.]+)/i',
                '/\b(line)\/([\w.]+)\/iab/i',
                '/(alipay)client\/([\w.]+)/i',
                '/(twitter)(?:and|\sf.+e\/([\w.]+))/i',
                '/(chromium|instagram|snapchat)[\/\s]([-\w.]+)/i'], ['name', 'version'],
            ['/\bgsa\/([\w.]+)\s.*safari\//i'], ['version', ['name', 'GSA']],
            ['/musical_ly(?:.+app_?version\/|_)([\w.]+)/i'], ['version', ['name', 'TikTok']],

            // Chrome Headless
            ['/headlesschrome(?:\/([\w.]+)|\s)/i'], ['version', ['name', 'Chrome Headless']],
            // Chrome WebView
            ['/\swv\).+(chrome)\/([\w.]+)/i'], [['name', 'Chrome WebView'], 'version'],
            // Android Browser
            ['/droid.+\sversion\/([\w.]+)\b.+(?:mobile safari|safari)/i'], ['version', ['name', 'Android Browser']],
            // Chrome/OmniWeb/Arora/Tizen/Nokia
            ['/(chrome|omniweb|arora|[tizenoka]{5}\s?browser)\/v?([\w.]+)/i'], ['name', 'version'],
            // Mobile Safari
            ['/version\/([\w.,]+)\s.*mobile\/\w+\s(safari)/i'], ['version', ['name', 'Mobile Safari']],
            // Safari & Safari Mobile
            ['/version\/([\w(.|,)]+)\s.*(mobile\s?safari|safari)/i'], ['version', 'name'],
            // Safari < 3.0
            ['/webkit.+?(mobile\s?safari|safari)(\/[\w.]+)/i'], ['name', ['version', $strMapperOldSafari]],
            ['/(webkit|khtml)\/([\w.]+)/i'], ['name', 'version'],

            // Gecko based
            ['/(navigator|netscape\d?)\/([-\w.]+)/i'], [['name', 'Netscape'], 'version'],
            ['/mobile\svr;\srv:([\w.]+)\).+firefox/i'], ['version', ['name', 'Firefox Reality']],
            ['/ekiohf.+(flow)\/([\w.]+)/i',
                '/(swiftfox)/i',
                '/(icedragon|iceweasel|camino|chimera|fennec|maemo browser|minimo|conkeror|klar)[\/\s]?([\w.+]+)/i',
                '/(seamonkey|k-meleon|icecat|iceape|firebird|phoenix|palemoon|basilisk|waterfox)\/([-\w.]+)$/i',
                '/(firefox)\/([\w.]+)/i',
                '/(mozilla)\/([\w.]+)\s.+rv:.+gecko\/\d+/i',
                '/(polaris|lynx|dillo|icab|doris|amaya|w3m|netsurf|sleipnir|obigo|mosaic|(?:go|ice|up)[.\s]?browser)[-\/\s]?v?([\w.]+)/i',
                '/(links)\s\(([\w.]+)/i',
                '/panasonic;(viera)/i'], ['name', 'version'],
            // Cobalt
            ['/(cobalt)\/([\w.]+)/i'], ['name', ['version', '/master.|lts./', '']],
        ];

        // ═══════════════════════════════════════
        //  CPU
        // ═══════════════════════════════════════
        $cpu = [
            ['/(amd|x(?:(?:86|64)[-_])?|wow|win)64[;)]/i'], [['architecture', 'amd64']],
            ['/(ia32(?=;))/i'], [['architecture', $lowerize]],
            ['/((?:i[346]|x)86)[;)]/i'], [['architecture', 'ia32']],
            ['/\b(aarch64|arm(v?8e?l?|_?64))\b/i'], [['architecture', 'arm64']],
            ['/\b(arm(?:v[67])?ht?n?[fl]p?)\b/i'], [['architecture', 'armhf']],
            // PocketPC mistakenly identified as PowerPC
            ['/windows\s(ce|mobile);\sppc;/i'], [['architecture', 'arm']],
            ['/((?:ppc|powerpc)(?:64)?)(?: mac|;|\))/i'], [['architecture', '/ower/', '', $lowerize]],
            ['/(sun4\w)[;)]/i'], [['architecture', 'sparc']],
            ['/(avr32|ia64(?=;)|68k(?=\))|\barm(?=v(?:[1-7]|[5-7]1)l?|;|eabi)|(?=atmel\s)avr|(?:irix|mips|sparc)(?:64)?\b|pa-risc)/i'], [['architecture', $lowerize]],
        ];

        // ═══════════════════════════════════════
        //  DEVICE
        // ═══════════════════════════════════════
        $device = [
            // ///////////////////////
            // MOBILES & TABLETS
            // ///////////////////////

            // Samsung
            ['/\b(sch-i[89]0\d|shw-m380s|sm-[ptx]\w{2,4}|gt-[pn]\d{2,4}|sgh-t8[56]9|nexus 10)/i'], ['model', ['vendor', 'Samsung'], ['type', 'tablet']],
            ['/\b((?:s[cgp]h|gt|sm)-\w+|sc[g-]?\d+a?|galaxy nexus)/i',
                '/samsung[-\s]([-\w]+)/i',
                '/sec-(sgh\w+)/i'], ['model', ['vendor', 'Samsung'], ['type', 'mobile']],

            // Apple
            ['/(?:\/|\()(ip(?:hone|od)[\w,\s]*)(?:\/|;)/i'], ['model', ['vendor', 'Apple'], ['type', 'mobile']],
            ['/\((ipad);[-\w),;\s]+apple/i',
                '/applecoremedia\/[\w.]+\s\((ipad)/i',
                '/\b(ipad)\d\d?,\d\d?[;\]].+ios/i'], ['model', ['vendor', 'Apple'], ['type', 'tablet']],
            ['/(macintosh);/i'], ['model', ['vendor', 'Apple']],

            // Sharp
            ['/\b(sh-?[altvz]?\d\d[a-ekm]?)/i'], ['model', ['vendor', 'Sharp'], ['type', 'mobile']],

            // Huawei
            ['/\b((?:ag[rs][23]?|bah2?|sht?|btv)-a?[lw]\d{2})\b(?!.+d\/s)/i'], ['model', ['vendor', 'Huawei'], ['type', 'tablet']],
            ['/(?:huawei|honor)([-\w\s]+)[;)]/i',
                '/\b(nexus 6p|\w{2,4}e?-[atu]?[ln][\dx][0-359c][adn]?)\b(?!.+d\/s)/i'], ['model', ['vendor', 'Huawei'], ['type', 'mobile']],

            // Xiaomi
            ['/\b(poco[\w\s]+|m2\d{3}j\d\d[a-z]{2})(?:\sbui|\))/i',
                '/\b;\s(\w+)\sbuild\/hm\1/i',
                '/\b(hm[-_\s]?note?[_\s]?(?:\d\w)?)\sbui/i',
                '/\b(redmi[-_\s]?[\w\s]+)(?:\sbui|\))/i',
                '/oid[^)]+;\s(m?[12][0-389][01]\w{3,6}[c-y])(?:\sbui|;\swv|\))/i',
                '/\b(mi[-_\s]?(?:a\d|one|one[_\s]plus|note lte|max|cc)?[_\s]?\d?\w?[_\s]?(?:plus|se|lite)?)(?:\sbui|\))/i'], [['model', '/_/', ' '], ['vendor', 'Xiaomi'], ['type', 'mobile']],
            ['/oid[^)]+;\s(2\d{4}(283|rpbf)[cgl])(?:\sbui|\))/i',
                '/\b(mi[-_\s]?pad[\w\s]+)(?:\sbui|\))/i'], [['model', '/_/', ' '], ['vendor', 'Xiaomi'], ['type', 'tablet']],

            // OPPO
            ['/;\s(\w+)\sbui.+\soppo/i',
                '/\b(cph[12]\d{3}|p(?:af|c[al]|d\w|e[ar])[mt]\d0|x9007|a101op)\b/i'], ['model', ['vendor', 'OPPO'], ['type', 'mobile']],
            ['/\b(opd2\d{3}a?)\sbui/i'], ['model', ['vendor', 'OPPO'], ['type', 'tablet']],

            // Vivo
            ['/vivo\s(\w+)(?:\sbui|\))/i',
                '/\b(v[12]\d{3}\w?[at])(?:\sbui|;)/i'], ['model', ['vendor', 'Vivo'], ['type', 'mobile']],

            // Realme
            ['/\b(rmx[1-3]\d{3})(?:\sbui|;|\))/i'], ['model', ['vendor', 'Realme'], ['type', 'mobile']],

            // Motorola
            ['/\b(milestone|droid(?:[2-4x]|\s(?:bionic|x2|pro|razr))?:?(?:\s4g)?)\b[\w\s]+build\//i',
                '/\bmot(?:orola)?[-\s](\w*)/i',
                '/((?:moto[\w()\s]+|xt\d{3,4}|nexus 6)(?=\sbui|\)))/i'], ['model', ['vendor', 'Motorola'], ['type', 'mobile']],
            ['/\b(mz60\d|xoom[2\s]{0,2})\sbuild\//i'], ['model', ['vendor', 'Motorola'], ['type', 'tablet']],

            // LG
            ['/((?=lg)?[vl]k-?\d{3})\sbui|\s3\.[-\w;\s]{10}lg?-([06cv9]{3,4})/i'], ['model', ['vendor', 'LG'], ['type', 'tablet']],
            ['/(lm(?:-?f100[nv]?|-[\w.]+)(?=\sbui|\))|nexus [45])/i',
                '/\blg[-e;\/\s]+((?!browser|netcast|android tv)\w+)/i',
                '/\blg-?(\w+)\sbui/i'], ['model', ['vendor', 'LG'], ['type', 'mobile']],

            // Lenovo
            ['/(ideatab[-\w\s]+)/i',
                '/lenovo\s?(s[56]000[-\w]+|tab[\w\s]+|yt[-\w]{6}|tb[-\w]{6})/i'], ['model', ['vendor', 'Lenovo'], ['type', 'tablet']],

            // Nokia
            ['/(?:maemo|nokia).*(n900|lumia\s\d+)/i',
                '/nokia[-_\s]?([-\w.]*)/i'], [['model', '/_/', ' '], ['vendor', 'Nokia'], ['type', 'mobile']],

            // Google
            ['/(pixel c)\b/i'], ['model', ['vendor', 'Google'], ['type', 'tablet']],
            ['/droid.+;\s(pixel[\daxl\s]{0,6})(?:\sbui|\))/i'], ['model', ['vendor', 'Google'], ['type', 'mobile']],

            // Sony
            ['/droid.+\s(a?\d[0-2]{2}so|[c-g]\d{4}|so[-gl]\w+|xq-a\w[4-7][12])(?=\sbui|\).+chrome\/(?![1-6]?\d\.))/i'], ['model', ['vendor', 'Sony'], ['type', 'mobile']],
            ['/sony tablet [ps]/i',
                '/\b(?:sony)?sgp\w+(?:\sbui|\))/i'], [['model', 'Xperia Tablet'], ['vendor', 'Sony'], ['type', 'tablet']],

            // OnePlus
            ['/\s(kb2005|in20[12]5|be20[12][59])\b/i',
                '/(?:one)?(?:plus)?\s(a\d0\d\d)(?:\sb|\))/i'], ['model', ['vendor', 'OnePlus'], ['type', 'mobile']],

            // Amazon
            ['/(alexa)webm/i',
                '/(kf[a-z]{2}wi|aeo[c-r]{2})(?:\sbui|\))/i',
                '/(kf[a-z]+)(?:\sbui|\)).+silk\//i'], ['model', ['vendor', 'Amazon'], ['type', 'tablet']],
            ['/((?:sd|kf)[0349hijor-uw]+)(?:\sbui|\)).+silk\//i'], [['model', '/(.+)/', 'Fire Phone $1'], ['vendor', 'Amazon'], ['type', 'mobile']],

            // BlackBerry
            ['/(playbook);[-\w),;\s]+(rim)/i'], ['model', 'vendor', ['type', 'tablet']],
            ['/\b((?:bb[a-f]|st[hv])100-\d)/i',
                '/\(bb10;\s(\w+)/i'], ['model', ['vendor', 'BlackBerry'], ['type', 'mobile']],

            // Asus
            ['/(?:\b|asus_)(transfo[prime\s]{4,10}\s\w+|eeepc|slider\s\w+|nexus 7|padfone|p00[cj])/i'], ['model', ['vendor', 'ASUS'], ['type', 'tablet']],
            ['/\s(z[bes]6[027][012][km][ls]|zenfone\s\d\w?)\b/i'], ['model', ['vendor', 'ASUS'], ['type', 'mobile']],

            // HTC
            ['/(nexus 9)/i'], ['model', ['vendor', 'HTC'], ['type', 'tablet']],
            ['/(htc)[-;_\s]{1,2}([\w\s]+(?=\)|\sbui)|\w+)/i',
                // ZTE
                '/(zte)[-\s]([\w\s]+?)(?:\sbui|\/|\))/i',
                '/(alcatel|geeksphone|nexian|panasonic(?!;|\.)|sony(?!-bra))[-_\s]?([-\w]*)/i'], ['vendor', ['model', '/_/', ' '], ['type', 'mobile']],

            // Acer
            ['/droid.+;\s([ab][1-7]-?[0178a]\d\d?)/i'], ['model', ['vendor', 'Acer'], ['type', 'tablet']],

            // Meizu
            ['/droid.+;\s(m[1-5]\snote)\sbui/i',
                '/\bmz-([-\w]{2,})/i'], ['model', ['vendor', 'Meizu'], ['type', 'mobile']],

            // Ulefone
            ['/;\s((?:power\s)?armor[\w\s]{0,8})(?:\sbui|\))/i'], ['model', ['vendor', 'Ulefone'], ['type', 'mobile']],

            // MIXED
            ['/(blackberry|benq|palm(?=-)|sonyericsson|acer|asus|dell|meizu|motorola|polytron|infinix|tecno)[-_\s]?([-\w]*)/i',
                '/(hp)\s([\w\s]+\w)/i',
                '/(asus)-?(\w+)/i',
                '/(microsoft);\s(lumia[\w\s]+)/i',
                '/(lenovo)[-_\s]?([-\w]+)/i',
                '/(jolla)/i',
                '/(oppo)\s?([\w\s]+)\sbui/i'], ['vendor', 'model', ['type', 'mobile']],

            ['/(kobo)\s(ereader|touch)/i',
                '/(archos)\s(gamepad2?)/i',
                '/(hp).+(touchpad(?!.+tablet)|tablet)/i',
                '/(kindle)\/([\w.]+)/i',
                '/(nook)[\w\s]+build\/(\w+)/i',
                '/(dell)\s(strea[kpr\d\s]*[\dko])/i',
                '/(le[-\s]+pan)[-\s]+(\w{1,9})\sbui/i',
                '/(trinity)[-\s]*(t\d{3})\sbui/i',
                '/(gigaset)[-\s]+(q\w{1,9})\sbui/i',
                '/(vodafone)\s([\w\s]+)(?:\)|\sbui)/i'], ['vendor', 'model', ['type', 'tablet']],

            // Surface Duo
            ['/(surface duo)/i'], ['model', ['vendor', 'Microsoft'], ['type', 'tablet']],
            // Fairphone
            ['/droid\s[\d.]+;\s(fp\du?)(?:\sb|\))/i'], ['model', ['vendor', 'Fairphone'], ['type', 'mobile']],
            // AT&T
            ['/(u304aa)/i'], ['model', ['vendor', 'AT&T'], ['type', 'mobile']],
            // Siemens
            ['/\bsie-(\w*)/i'], ['model', ['vendor', 'Siemens'], ['type', 'mobile']],
            // RCA Tablets
            ['/\b(rct\w+)\sb/i'], ['model', ['vendor', 'RCA'], ['type', 'tablet']],
            // Dell Venue Tablets
            ['/\b(venue[\d\s]{2,7})\sb/i'], ['model', ['vendor', 'Dell'], ['type', 'tablet']],
            // Verizon Tablet
            ['/\b(q(?:mv|ta)\w+)\sb/i'], ['model', ['vendor', 'Verizon'], ['type', 'tablet']],
            // Barnes & Noble Tablet
            ['/\b(?:barnes[&\s]+noble\s|bn[rt])([\w+\s]*)\sb/i'], ['model', ['vendor', 'Barnes & Noble'], ['type', 'tablet']],
            ['/\b(tm\d{3}\w+)\sb/i'], ['model', ['vendor', 'NuVision'], ['type', 'tablet']],
            // ZTE K Series Tablet
            ['/\b(k88)\sb/i'], ['model', ['vendor', 'ZTE'], ['type', 'tablet']],
            // ZTE Nubia
            ['/\b(nx\d{3}j)\sb/i'], ['model', ['vendor', 'ZTE'], ['type', 'mobile']],
            // Swiss GEN Mobile
            ['/\b(gen\d{3})\sb.+49h/i'], ['model', ['vendor', 'Swiss'], ['type', 'mobile']],
            // Swiss ZUR Tablet
            ['/\b(zur\d{3})\sb/i'], ['model', ['vendor', 'Swiss'], ['type', 'tablet']],
            // Zeki Tablets
            ['/\b((zeki)?tb.*\b)\sb/i'], ['model', ['vendor', 'Zeki'], ['type', 'tablet']],
            // Dragon Touch Tablet
            ['/\b([yr]\d{2})\sb/i',
                '/\b(dragon[-\s]+touch\s|dt)(\w{5})\sb/i'], [['vendor', 'Dragon Touch'], 'model', ['type', 'tablet']],
            // Insignia Tablets
            ['/\b(ns-?\w{0,9})\sb/i'], ['model', ['vendor', 'Insignia'], ['type', 'tablet']],
            // NextBook Tablets
            ['/\b((nxa|next)-?\w{0,9})\sb/i'], ['model', ['vendor', 'NextBook'], ['type', 'tablet']],
            // Voice Xtreme Phones
            ['/\b(xtreme_)?(v(1[045]|2[015]|[3469]0|7[05]))\sb/i'], [['vendor', 'Voice'], 'model', ['type', 'mobile']],
            // LvTel Phones
            ['/\b(lvtel-)?(v1[12])\sb/i'], [['vendor', 'LvTel'], 'model', ['type', 'mobile']],
            // Essential PH-1
            ['/\b(ph-1)\s/i'], ['model', ['vendor', 'Essential'], ['type', 'mobile']],
            // Envizen Tablets
            ['/\b(v(100md|700na|7011|917g).*\b)\sb/i'], ['model', ['vendor', 'Envizen'], ['type', 'tablet']],
            // MachSpeed Tablets
            ['/\b(trio[-\w.\s]+)\sb/i'], ['model', ['vendor', 'MachSpeed'], ['type', 'tablet']],
            // Rotor Tablets
            ['/\btu_(1491)\sb/i'], ['model', ['vendor', 'Rotor'], ['type', 'tablet']],
            // Nvidia Shield Tablets
            ['/(shield[\w\s]+)\sb/i'], ['model', ['vendor', 'Nvidia'], ['type', 'tablet']],
            // Sprint Phones
            ['/(sprint)\s(\w+)/i'], ['vendor', 'model', ['type', 'mobile']],
            // Microsoft Kin
            ['/(kin\.[onetw]{3})/i'], [['model', '/\./', ' '], ['vendor', 'Microsoft'], ['type', 'mobile']],
            // Zebra
            ['/droid.+;\s(cc6666?|et5[16]|mc[239][23]x?|vc8[03]x?)\)/i'], ['model', ['vendor', 'Zebra'], ['type', 'tablet']],
            ['/droid.+;\s(ec30|ps20|tc[2-8]\d[kx])\)/i'], ['model', ['vendor', 'Zebra'], ['type', 'mobile']],

            // /////////////////
            // SMARTTVS
            // /////////////////
            ['/smart-tv.+(samsung)/i'], ['vendor', ['type', 'smarttv']],
            ['/hbbtv.+maple;(\d+)/i'], [['model', '/^/', 'SmartTV'], ['vendor', 'Samsung'], ['type', 'smarttv']],
            ['/(nux;\snetcast.+smarttv|lg\s(netcast\.tv-201\d|android tv))/i'], [['vendor', 'LG'], ['type', 'smarttv']],
            ['/(apple)\s?tv/i'], ['vendor', ['model', 'Apple TV'], ['type', 'smarttv']],
            ['/crkey/i'], [['model', 'Chromecast'], ['vendor', 'Google'], ['type', 'smarttv']],
            ['/droid.+aft(\w+)(?:\sbui|\))/i'], ['model', ['vendor', 'Amazon'], ['type', 'smarttv']],
            ['/\(dtv[);].+(aquos)/i',
                '/(aquos-tv[\w\s]+)\)/i'], ['model', ['vendor', 'Sharp'], ['type', 'smarttv']],
            ['/(bravia[\w\s]+)(?:\sbui|\))/i'], ['model', ['vendor', 'Sony'], ['type', 'smarttv']],
            ['/(mitv-\w{5})\sbui/i'], ['model', ['vendor', 'Xiaomi'], ['type', 'smarttv']],
            ['/Hbbtv.*(technisat)\s(.*);/i'], ['vendor', 'model', ['type', 'smarttv']],
            ['/\b(roku)[\dx]*[\)\/]((?:dvp-)?[\d.]*)/i',
                '/hbbtv\/\d+\.\d+\.\d+\s+\([\w+\s]*;\s*(\w[^;]*);([^;]*)/i'], [['vendor', fn ($m) => $m !== null ? trim($m) : null], ['model', fn ($m) => $m !== null ? trim($m) : null], ['type', 'smarttv']],
            ['/\b(android tv|smart[-\s]?tv|opera tv|tv;\srv:)\b/i'], [['type', 'smarttv']],

            // /////////////////
            // CONSOLES
            // /////////////////
            ['/(ouya)/i',
                '/(nintendo)\s([wids3utch]+)/i'], ['vendor', 'model', ['type', 'console']],
            ['/droid.+;\s(shield)\sbui/i'], ['model', ['vendor', 'Nvidia'], ['type', 'console']],
            ['/(playstation\s[345portablevi]+)/i'], ['model', ['vendor', 'Sony'], ['type', 'console']],
            ['/\b(xbox(?:\sone)?(?!;\sxbox))[);\s]/i'], ['model', ['vendor', 'Microsoft'], ['type', 'console']],

            // /////////////////
            // WEARABLES
            // /////////////////
            ['/((pebble))app/i'], ['vendor', 'model', ['type', 'wearable']],
            ['/(watch)(?:\s?os[,\/]|\d,\d\/)([\d.]+)/i'], ['model', ['vendor', 'Apple'], ['type', 'wearable']],
            ['/droid.+;\s(glass)\s\d/i'], ['model', ['vendor', 'Google'], ['type', 'wearable']],
            ['/droid.+;\s(wt63?0{2,3})\)/i'], ['model', ['vendor', 'Zebra'], ['type', 'wearable']],
            ['/(quest(?:\s\d|\spro)?)/i'], ['model', ['vendor', 'Facebook'], ['type', 'wearable']],

            // /////////////////
            // EMBEDDED
            // /////////////////
            ['/(tesla)(?:\sqtcarbrowser|\/[-\w.]+)/i'], ['vendor', ['type', 'embedded']],
            ['/(aeobc)\b/i'], ['model', ['vendor', 'Amazon'], ['type', 'embedded']],

            // //////////////////
            // MIXED (GENERIC)
            // /////////////////
            ['/droid\s.+?;\s([^;]+?)(?:\sbui|;\swv\)|\)\sapplew).+?\smobile safari/i'], ['model', ['type', 'mobile']],
            ['/droid\s.+?;\s([^;]+?)(?:\sbui|\)\sapplew).+?(?!\smobile)\ssafari/i'], ['model', ['type', 'tablet']],
            ['/\b((tablet|tab)[;\/]|focus\/\d(?!.+mobile))/i'], [['type', 'tablet']],
            ['/(phone|mobile(?:[;\/]|\s[\w\/.]*safari)|pda(?=.+windows ce))/i'], [['type', 'mobile']],
            ['/(android[-\w.\s]{0,9});.+buil/i'], ['model', ['vendor', 'Generic']],
        ];

        // ═══════════════════════════════════════
        //  ENGINE
        // ═══════════════════════════════════════
        $engine = [
            ['/windows.+\sedge\/([\w.]+)/i'], ['version', ['name', 'EdgeHTML']],
            ['/webkit\/537\.36.+chrome\/(?!27)([\w.]+)/i'], ['version', ['name', 'Blink']],
            ['/(presto)\/([\w.]+)/i',
                '/(webkit|trident|netfront|netsurf|amaya|lynx|w3m|goanna)\/([\w.]+)/i',
                '/ekioh(flow)\/([\w.]+)/i',
                '/(khtml|tasman|links)[\/\s]\(?([\w.]+)/i',
                '/(icab)[\/\s]([23]\.[\d.]+)/i',
                '/\b(libweb)/i'], ['name', 'version'],
            ['/rv:([\w.]{1,9})\b.+(gecko)/i'], ['version', 'name'],
        ];

        // ═══════════════════════════════════════
        //  OS
        // ═══════════════════════════════════════
        $os = [
            // Windows
            ['/microsoft\s(windows)\s(vista|xp)/i'], ['name', 'version'],
            ['/(windows\s(?:phone(?:\sos)?|mobile))[\/\s]?([.\w\s]*)/i'], ['name', ['version', $strMapperWinVer]],
            ['/windows\snt\s6\.2;\s(arm)/i',
                '/windows[\/\s]?([ntce\d.\s]+\w)(?!.+xbox)/i',
                '/(?:win(?=[39n])|win\s9x\s)([nt\d.]+)/i'], [['version', $strMapperWinVer], ['name', 'Windows']],

            // iOS/macOS
            ['/ip[honead]{2,4}\b(?:.*os\s(\w+)\slike\smac|;\sopera)/i',
                '/(?:ios;fbsv\/|iphone.+ios[\/\s])([\d.]+)/i',
                '/cfnetwork\/.+darwin/i'], [['version', '/_/', '.'], ['name', 'iOS']],
            ['/(mac\sos\sx)\s?([\w.\s]*)/i',
                '/(macintosh|mac_powerpc\b)(?!.+haiku)/i'], [['name', 'Mac OS'], ['version', '/_/', '.']],

            // Mobile OSes
            ['/droid\s([\w.]+)\b.+(android[-\s]x86|harmonyos)/i'], ['version', 'name'],
            ['/(android|webos|qnx|bada|rim tablet os|maemo|meego|sailfish)[-\/\s]?([\w.]*)/i',
                '/(blackberry)\w*\/([\w.]*)/i',
                '/(tizen|kaios)[\/\s]([\w.]+)/i',
                '/\((series40);/i'], ['name', 'version'],
            ['/\(bb(10);/i'], ['version', ['name', 'BlackBerry']],
            ['/(?:symbian\s?os|symbos|s60(?=;)|series60)[-\/\s]?([\w.]*)/i'], ['version', ['name', 'Symbian']],
            ['/mozilla\/[\d.]+\s\((?:mobile|tablet|tv|mobile;\s[\w\s]+);\srv:.+\sgecko\/([\w.]+)/i'], ['version', ['name', 'Firefox OS']],
            ['/web0s;.+rt(tv)/i',
                '/\b(?:hp)?wos(?:browser)?\/([\w.]+)/i'], ['version', ['name', 'webOS']],
            ['/watch(?:\s?os[,\/]|\d,\d\/)([\d.]+)/i'], ['version', ['name', 'watchOS']],

            // Google Chromecast
            ['/crkey\/([\d.]+)/i'], ['version', ['name', 'Chromecast']],
            ['/(cros)\s\w+(?:\)|\s([\w.]+)\b)/i'], [['name', 'Chromium OS'], 'version'],

            // Smart TVs
            ['/panasonic;(viera)/i',
                '/(netrange)mmh/i',
                '/(nettv)\/(\d+\.[\w.]+)/i'], ['name', 'version'],

            // Console
            ['/(nintendo|playstation)\s([wids345portablevuch]+)/i',
                '/(xbox);\s+xbox\s([^);]+)/i'], ['name', 'version'],

            // Other
            ['/\b(joli|palm)\b\s?(?:os)?\/?([\w.]*)/i',
                '/(mint)[\/(\s]?(\w*)/i',
                '/(mageia|vectorlinux)[;\s]/i',
                '/([kxln]?ubuntu|debian|suse|opensuse|gentoo|arch(?=\slinux)|slackware|fedora|mandriva|centos|pclinuxos|red\s?hat|zenwalk|linpus|raspbian|plan 9|minix|risc os|contiki|deepin|manjaro|elementary os|sabayon|linspire)(?:\sgnu\/linux)?(?:\senterprise)?(?:[-\s]linux)?(?:-gnu)?[-\/\s]?(?!chrom|package)([-\w.]*)/i',
                '/(hurd|linux)\s?([\w.]*)/i',
                '/(gnu)\s?([\w.]*)/i'], ['name', 'version'],
            ['/(sunos)\s?([\w.]*)/i'], [['name', 'Solaris'], 'version'],
            ['/(?:(?:open)?solaris)[-\/\s]?([\w.]*)/i'], ['version', ['name', 'Solaris']],
            ['/(aix)\s((\d)(?=[.)\s])[\w.])*/i',
                '/\b(beos|os\/2|amigaos|morphos|openvms|fuchsia|hp-ux|serenityos)/i',
                '/(unix)\s?([\w.]*)/i'], ['name', 'version'],
            ['/\b([-e-hrntopcs]{0,5}bsd|dragonfly)[\/\s]?(?!amd|[ix346]{1,2}86)([\w.]*)/i'], ['name', 'version'],
            ['/(haiku)\s(\w+)/i'], ['name', 'version'],
        ];

        self::$regexes = compact('browser', 'cpu', 'device', 'engine', 'os');

        return self::$regexes;
    }
}
