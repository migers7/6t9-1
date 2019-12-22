<?php

include_once 'config.php';

class ConstantUtils
{
    public static function getCountryList()
    {
        return ["Andorra", "United Arab Emirates (UAE)", "Afghanistan", "Antigua and Barbuda", "Anguilla",
            "Albania", "Armenia", "Angola", "Antarctica", "Argentina",
            "American Samoa", "Austria", "Australia", "Aruba", "Aland Islands",
            "Azerbaijan", "Bosnia And Herzegovina", "Barbados", "Bangladesh", "Belgium",
            "Burkina Faso", "Bulgaria", "Bahrain", "Burundi", "Benin",
            "Saint Barthélemy", "Bermuda", "Brunei Darussalam", "Bolivia, Plurinational State Of", "Brazil",
            "Bahamas", "Bhutan", "Botswana", "Belarus", "Belize",
            "Canada", "Cocos (keeling) Islands", "Congo, The Democratic Republic Of The", "Central African Republic", "Congo",
            "Switzerland", "Côte D'ivoire", "Cook Islands", "Chile", "Cameroon",
            "China", "Colombia", "Costa Rica", "Cuba", "Cape Verde",
            "Christmas Island", "Cyprus", "Czech Republic", "Germany", "Djibouti",
            "Denmark", "Dominica", "Dominican Republic", "Algeria", "Ecuador",
            "Estonia", "Egypt", "Eritrea", "Spain", "Ethiopia",
            "Finland", "Fiji", "Falkland Islands (malvinas)", "Micronesia, Federated States Of", "Faroe Islands",
            "France", "Gabon", "United Kingdom", "Grenada", "Georgia",
            "French Guyana", "Guernsey", "Ghana", "Gibraltar", "Greenland",
            "Gambia", "Guinea", "Guadeloupe", "Equatorial Guinea", "Greece",
            "Guatemala", "Guam", "Guinea-bissau", "Guyana", "Hong Kong",
            "Honduras", "Croatia", "Haiti", "Hungary", "Indonesia",
            "Ireland", "Israel", "Isle Of Man", "Iceland", "India",
            "British Indian Ocean Territory", "Iraq", "Iran, Islamic Republic Of", "Italy", "Jersey",
            "Jamaica", "Jordan", "Japan", "Kenya", "Kyrgyzstan",
            "Cambodia", "Kiribati", "Comoros", "Saint Kitts and Nevis", "North Korea",
            "South Korea", "Kuwait", "Cayman Islands", "Kazakhstan", "Lao People's Democratic Republic",
            "Lebanon", "Saint Lucia", "Liechtenstein", "Sri Lanka", "Liberia",
            "Lesotho", "Lithuania", "Luxembourg", "Latvia", "Libya",
            "Morocco", "Monaco", "Moldova, Republic Of", "Montenegro", "Saint Martin",
            "Madagascar", "Marshall Islands", "Macedonia, The Former Yugoslav Republic Of", "Mali", "Myanmar",
            "Mongolia", "Macao", "Northern Mariana Islands", "Martinique", "Mauritania",
            "Montserrat", "Malta", "Mauritius", "Maldives", "Malawi",
            "Mexico", "Malaysia", "Mozambique", "Namibia", "New Caledonia",
            "Niger", "Norfolk Islands", "Nigeria", "Nicaragua", "Netherlands",
            "Norway", "Nepal", "Nauru", "Niue", "New Zealand",
            "Oman", "Panama", "Peru", "French Polynesia", "Papua New Guinea",
            "Philippines", "Pakistan", "Poland", "Saint Pierre And Miquelon", "Pitcairn",
            "Puerto Rico", "Palestine", "Portugal", "Palau", "Paraguay",
            "Qatar", "Réunion", "Romania", "Serbia", "Russian Federation",
            "Rwanda", "Saudi Arabia", "Solomon Islands", "Seychelles", "Sudan",
            "Sweden", "Singapore", "Saint Helena, Ascension And Tristan Da Cunha", "Slovenia", "Slovakia",
            "Sierra Leone", "San Marino", "Senegal", "Somalia", "Suriname",
            "South Sudan", "Sao Tome And Principe", "El Salvador", "Sint Maarten", "Syrian Arab Republic",
            "Swaziland", "Turks and Caicos Islands", "Chad", "Togo", "Thailand",
            "Tajikistan", "Tokelau", "Timor-leste", "Turkmenistan", "Tunisia",
            "Tonga", "Turkey", "Trinidad &amp; Tobago", "Tuvalu", "Taiwan",
            "Tanzania, United Republic Of", "Ukraine", "Uganda", "United States", "Uruguay",
            "Uzbekistan", "Holy See (vatican City State)", "Saint Vincent &amp; The Grenadines", "Venezuela, Bolivarian Republic Of", "British Virgin Islands",
            "US Virgin Islands", "Viet Nam", "Vanuatu", "Wallis And Futuna", "Samoa",
            "Kosovo", "Yemen", "Mayotte", "South Africa", "Zambia",
            "Zimbabwe"];
    }

    public static function isValidCountry($country)
    {
        return in_array($country, self::getCountryList());
    }

    public static function isValidEmail($username, $email)
    {
        $domains = ["gmail.com", "yahoo.com", "ymail.com", "hotmail.com", "live.com", "outlook.com", "icloud.com"];
        $arr = explode("@", $email);
        if (count($arr) != 2) return "Invalid email address";
        if ($arr[1] == "gmail.com") {
            $basic = $arr[0];
            $basic = str_replace(".", "", $basic);
            $basic = str_replace("+", "", $basic);
            $email = $basic . "@gmail.com";
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) == false) return "Invalid email address";
        if (in_array($arr[1], $domains) == false) return "We do not support email address of this domain. Please use gmail, yahoo, ymail, hotmail, live, outlook or icloud.";
        if (ApiHelper::rowExists("SELECT id FROM users WHERE email = '$email'")) return "This email address is already in use";
        return null;
    }
}