<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'user.php';
include_once 'auth_util.php';

$headers = apache_request_headers();

$validationError = AuthUtil::validateApp($headers);
if ($validationError != null) {
    echo $validationError;
    exit(0);
}


/*echo buildErrorResponse("Registration is closed. Will be open soon.");
exit(0);*/

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

$countryArray = [
    "Andorra", "United Arab Emirates (UAE)", "Afghanistan", "Antigua and Barbuda", "Anguilla",
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
    "Zimbabwe"
];

if (in_array($params["country"], $countryArray) == false) {
    echo buildErrorResponse("The country you selected does not exist or we do not provide our app for it.");
} else {
    $user = new User();
    $user->username = $params["username"];
    $user->email = $params["email"];
    $user->password = sha1($params["password"]);
    $user->gender = $params["gender"];
    $user->memberSince = $params["memberSince"];
    $user->country = $params["country"];
    echo $user->add();
}
