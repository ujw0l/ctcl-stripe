window.addEventListener('DOMContentLoaded', () => {
    if (null != document.querySelector('#ctcl-stripe-card-el')) {
        // Create a Stripe client.
        var stripe = Stripe(ctclStripeParams.stripePubKey);
        // Create an instance of Elements.
        var elements = stripe.elements();
        // Custom styling can be passed to options when creating an Element.
        // (Note that this demo uses a wider set of styles than the guide below.)
        var style = {
            base: {
                color: '#32325d',
                fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                fontSmoothing: 'antialiased',
                fontSize: '16px',
                '::placeholder': {
                    color: '#aab7c4'
                },
            },
            invalid: {
                color: '#fa755a',
                iconColor: '#fa755a'
            }
        };

        // Create an instance of the card Element.
        var card = elements.create('card', {
            style: style
        });

        // Add an instance of the card Element into the `card-element` <div>.
        card.mount('#ctcl-stripe-card-el');

        // Handle real-time validation errors from the card Element.
        card.addEventListener('change', function (event) {
            var displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });



        document.querySelector('#ctcl-checkout-from').addEventListener('submit', e => {

            if (e.target.querySelector('#ctcl_stripe').checked) {
                e.preventDefault();

                stripe.createToken(card).then((result) => {
                    if (result.error) {
                        // Inform the user if there was an error.
                        var errorElement = document.getElementById('card-errors');
                        errorElement.textContent = result.error.message;
                    } else {
                        // Send the token to your server.
                        // Insert the token ID into the form so it gets submitted to the server
                        var form = document.querySelector('#ctcl-checkout-from');
                        var hiddenInput = document.createElement('input');
                        hiddenInput.setAttribute('type', 'hidden');
                        hiddenInput.setAttribute('name', 'stripe_token');
                        hiddenInput.setAttribute('value', result.token.id);
                        form.appendChild(hiddenInput);

                        // Submit the form
                        form.submit();
                    }
                });
            }

        })

    }

});