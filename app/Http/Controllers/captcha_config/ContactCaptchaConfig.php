<?php

// BotDetect PHP Captcha configuration options
// more details here: http://captcha.com/doc/php/howto/captcha-configuration.html
// ---------------------------------------------------------------------------

$LBD_CaptchaConfig = CaptchaConfiguration::GetSettings();

$LBD_CaptchaConfig->CodeLength = CaptchaRandomization::GetRandomCodeLength(4, 6);
