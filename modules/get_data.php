<?php
include "flag.php";
include "ipinfo.php";
include "shadowsocks.php";
include "vmess.php";
include "xray.php";
include "ping.php";

function convert_to_iran_time($utc_timestamp)
{
    $utc_datetime = new DateTime($utc_timestamp);
    $utc_datetime->setTimezone(new DateTimeZone("Asia/Tehran"));
    return $utc_datetime->format("Y-m-d H:i:s");
}

function get_config($channel, $type)
{
    // Fetch the content of the Telegram channel URL
    $get = file_get_contents("https://t.me/s/" . $channel);

    // Load channels_assets JSON data
    $channels_assets = json_decode(file_get_contents("modules/channels/channels_assets.json"), true);

    // Determine the pattern and perform matching based on the type
    if ($type === "vmess") {
        preg_match_all(
            '/vmess:\/\/[^"]+(?:[^<]+<[^<]+)*<time datetime="([^"]+)"/',
            $get,
            $matches
        );
        $patern_config = "#vmess://(.*?)<#";
    } elseif ($type === "vless") {
        preg_match_all(
            '/vless:\/\/[^"]+(?:[^<]+<[^<]+)*<time datetime="([^"]+)"/',
            $get,
            $matches
        );
        $patern_config = "#vless://(.*?)<#";
    } elseif ($type === "trojan") {
        preg_match_all(
            '/trojan:\/\/[^"]+(?:[^<]+<[^<]+)*<time datetime="([^"]+)"/',
            $get,
            $matches
        );
        $patern_config = "#trojan://(.*?)<#";
    } elseif ($type === "ss") {
        preg_match_all(
            '/[^vmle]ss:\/\/[^"]+(?:[^<]+<[^<]+)*<time datetime="([^"]+)"/',
            $get,
            $matches
        );
        $patern_config = "#[^vmle]ss://(.*?)<#";
    }

    // Extract configurations based on the pattern
    preg_match_all($patern_config, $get, $configs);
    $final_data = [];
    $key_limit = count($configs[1]) - 3;

    // Iterate through each configuration
    foreach ($configs[1] as $key => $config) {
        if ($key >= $key_limit) {
            // Check for ellipsis or invalid characters in the config
            if (stripos($config, "…") !== false or stripos($config, "...") !== false) {
                null;
            } else {
                // Process the configuration based on the type
                if (strpos($config, "<br/>") !== false) {
                    $config = substr($config, 0, strpos($config, "<br/>"));
                }
                if ($type === "vmess") {
                    // Decode the vmess configuration
                    $the_config = decode_vmess($type . "://" . $config);

                    // Extract IP and port from the decoded configuration
                    $ip = !empty($the_config["sni"])
                        ? $the_config["sni"]
                        : (!empty($the_config["host"])
                            ? $the_config["host"]
                            : $the_config["add"]);
                    $port = $the_config["port"];

                    // Ping the IP and port to get the response time
                    @$ping_data = ping($ip, $port);

                    // If ping data is available
                    if ($ping_data !== "unavailable") {
                        // Get IP information (country) and flag
                        $ip_info = ip_info($ip);
                        if (isset($ip_info["country"])) {
                            $location = $ip_info["country"];
                            $flag = getFlags($location);
                        } else {
                            $flag = "🚩";
                        }

                        // Update the configuration with channel info, flag, and ping data
                        $the_config["ps"] =
                            "@" . $channel . "|" . $flag . "|" . $ping_data;

                        // Encode the updated configuration
                        $final_config = encode_vmess($the_config);

                        // Build the final data array with channel, type, config, and time
                        $final_data[$key]["channel"]['username'] = $channel;
                        $final_data[$key]["channel"]['title'] = $channels_assets[$channel]['title'];
                        $final_data[$key]["channel"]['logo'] = $channels_assets[$channel]['logo'];
                        $final_data[$key]["type"] = $type;
                        $final_data[$key]["config"] = $final_config;
                        $final_data[$key]["time"] = convert_to_iran_time(
                            $matches[1][$key]
                        );
                    }
                } elseif ($type === "vless") {
                    // Parse the vless configuration
                    $the_config = parseProxyUrl(
                        $type . "://" . $config,
                        "vless"
                    );

                    // Extract IP and port from the parsed vless configuration
                    $ip = !empty($the_config["params"]["sni"])
                        ? $the_config["params"]["sni"]
                        : (!empty($the_config["params"]["host"])
                            ? $the_config["params"]["host"]
                            : $the_config["hostname"]);
                    $port = $the_config["port"];

                    // Ping the IP and port to get the response time
                    @$ping_data = ping($ip, $port);

                    // If ping data is available
                    if ($ping_data !== "unavailable") {
                        // Get IP information (country) and flag
                        $ip_info = ip_info($ip);
                        if (isset($ip_info["country"])) {
                            $location = $ip_info["country"];
                            $flag = getFlags($location);
                        } else {
                            $flag = "🚩";
                        }

                        // Update the configuration with channel info, flag, and ping data
                        if (stripos($config, "reality") !== false) {
                            $the_config["hash"] =
                                "REALITY|" .
                                "@" .
                                $channel .
                                "|" .
                                $flag .
                                "|" .
                                $ping_data;
                            $type = "reality";
                        } else {
                            $the_config["hash"] =
                                "@" . $channel . "|" . $flag . "|" . ping($ip, $port);
                        }

                        // Build the final vless configuration
                        $final_config = buildProxyUrl($the_config, "vless");

                        // Build the final data array with channel, type, config, and time
                        $final_data[$key]["channel"]['username'] = $channel;
                        $final_data[$key]["channel"]['title'] = $channels_assets[$channel]['title'];
                        $final_data[$key]["channel"]['logo'] = $channels_assets[$channel]['logo'];
                        $final_data[$key]["type"] = $type;
                        $final_data[$key]["config"] = urldecode($final_config);
                        $final_data[$key]["time"] = convert_to_iran_time(
                            $matches[1][$key]
                        );
                    }
                } elseif ($type === "trojan") {
                    // Parse the trojan configuration
                    $the_config = parseProxyUrl($type . "://" . $config);

                    // Extract IP and port from the parsed trojan configuration
                    $ip = !empty($the_config["params"]["sni"])
                        ? $the_config["params"]["sni"]
                        : (!empty($the_config["params"]["host"])
                            ? $the_config["params"]["host"]
                            : $the_config["hostname"]);
                    $port = $the_config["port"];

                    // Ping the IP and port to get the response time
                    @$ping_data = ping($ip, $port);

                    // If ping data is available
                    if ($ping_data !== "unavailable") {
                        // Get IP information (country) and flag
                        $ip_info = ip_info($ip);
                        if (isset($ip_info["country"])) {
                            $location = $ip_info["country"];
                            $flag = getFlags($location);
                        } else {
                            $flag = "🚩";
                        }

                        // Update the configuration with channel info, flag, and ping data
                        $the_config["hash"] =
                            "@" . $channel . "|" . $flag . "|" . $ping_data;

                        // Build the final trojan configuration
                        $final_config = buildProxyUrl($the_config);

                        // Build the final data array with channel, type, config, and time
                        $final_data[$key]["channel"]['username'] = $channel;
                        $final_data[$key]["channel"]['title'] = $channels_assets[$channel]['title'];
                        $final_data[$key]["channel"]['logo'] = $channels_assets[$channel]['logo'];
                        $final_data[$key]["type"] = $type;
                        $final_data[$key]["config"] = urldecode($final_config);
                        $final_data[$key]["time"] = convert_to_iran_time(
                            $matches[1][$key]
                        );
                    }
                } elseif ($type === "ss") {
                    // Parse the shadowsocks configuration
                    $the_config = ParseShadowsocks($type . "://" . $config);

                    // Extract IP and port from the parsed shadowsocks configuration
                    $ip = $the_config["server_address"];
                    $port = $the_config["server_port"];

                    // Ping the IP and port to get the response time
                    @$ping_data = ping($ip, $port);
                    // If ping data is available
                    if ($ping_data !== "unavailable") {
                        // Get IP information (country) and flag
                        $ip_info = ip_info($ip);
                        if (isset($ip_info["country"])) {
                            $location = $ip_info["country"];
                            $flag = getFlags($location);
                        } else {
                            $flag = "🚩";
                        }

                        // Update the configuration with channel info, flag, and ping data
                        $the_config["remarks"] =
                            "@" . $channel . "|" . $flag . "|" . $ping_data;

                        // Build the final shadowsocks configuration
                        $final_config = BuildShadowsocks($the_config);

                        // Build the final data array with channel, type, config, and time
                        $final_data[$key]["channel"]['username'] = $channel;
                        $final_data[$key]["channel"]['title'] = $channels_assets[$channel]['title'];
                        $final_data[$key]["channel"]['logo'] = $channels_assets[$channel]['logo'];
                        $final_data[$key]["type"] = $type;
                        $final_data[$key]["config"] = urldecode($final_config);
                        $final_data[$key]["time"] = convert_to_iran_time(
                            $matches[1][$key]
                        );
                    }
                }
            }
        }
    }

    // Return the final data array
    return $final_data;
}
?>
