function challengeStarting() {

    var hash = window.location.hash.split("|");

    if (hash && hash[0] === '#OvriChallenge') {

        if (hash[1]) {

            document.getElementById('OvriIframeThree').src = 'https://api.ovri.app/payment/Start_3ds/' + hash[1];

            jQuery(function ($) {

                $('#modalThreePanel').modal('show');

            });

        }

    }

}

function locationHashChanged() {

    challengeStarting();

}



window.onhashchange = locationHashChanged;