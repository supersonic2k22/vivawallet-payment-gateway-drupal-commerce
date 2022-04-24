(function (PayPal) {
  PayPal.Donation.Button({
    env: 'production',
    hosted_button_id: 'L2PVSYTGQUECW',
    image: {
      src: 'https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif',
      alt: 'Donate with PayPal button',
      title: 'PayPal - The safer, easier way to pay online!',
    }
  }).render('#donate-button');
})(PayPal);
