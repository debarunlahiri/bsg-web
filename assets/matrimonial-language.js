(() => {
    const translations = {
        hi: {
            title: 'वैवाहिक परिचय सम्मेलन 2026 | भटनागर सभा गाजियाबाद',
            brandName: 'भटनागर सभा',
            brandLocation: 'गाजियाबाद (पंजी.)',
            eventDetails: 'कार्यक्रम विवरण',
            invocation: 'जय श्री चित्रगुप्त भगवान',
            silverJubilee: 'रजत जयंती वर्ष',
            heroLineOne: '25वां कायस्थ',
            heroLineTwo: 'वैवाहिक परिचय सम्मेलन',
            heroLead: 'विवाह योग्य युवक-युवतियों और उनके परिवारों के लिए परिचय, संवाद और नए संबंधों का विश्वसनीय मंच।',
            dateLabel: 'दिनांक',
            eventDate: 'रविवार, 20 दिसंबर 2026',
            timeLabel: 'समय',
            eventTime: 'प्रातः 10 बजे – सायं 4 बजे',
            venueLabel: 'स्थान',
            venue: 'महाराजा अग्रसेन भवन, लोहिया नगर',
            viewDetails: 'पूरा विवरण देखें',
            contact: 'संपर्क करें',
            officialInvitation: 'कार्यक्रम का आधिकारिक आमंत्रण',
            familyInvited: 'परिवार सहित आमंत्रित',
            familyDescription: 'बच्चों के साथ उपस्थित होने से परिचय और संबंध तय होने की संभावना बढ़ती है।',
            deadline: 'अंतिम तिथि: 15 नवंबर 2026',
            deadlineDescription: 'फॉर्म और भुगतान की जानकारी निर्धारित तिथि तक भेजें।',
            trustedEvent: 'विश्वसनीय आयोजन',
            trustedDescription: 'भटनागर सभा गाजियाबाद द्वारा आयोजित 25वां परिचय सम्मेलन।',
            registrationDetails: 'पंजीकरण विवरण',
            paymentInformation: 'भुगतान की जानकारी',
            paymentInstruction: 'भुगतान के बाद Transaction ID, दिनांक और स्क्रीनशॉट फॉर्म में अवश्य भरें।',
            bankAccount: 'बैंक खाता',
            ifscCode: 'IFSC कोड',
            accountName: 'खाता नाम: भटनागर सभा गाजियाबाद',
            upiNumber: 'UPI नंबर',
            upiAccepted: 'UPI के माध्यम से भुगतान स्वीकार है',
            needHelp: 'सहायता चाहिए?',
            contactOrganizers: 'आयोजकों से संपर्क करें',
            president: 'अध्यक्ष',
            pramodName: 'प्रमोद भटनागर',
            generalSecretary: 'महासचिव',
            kkName: 'के. के. भटनागर',
            committeeHead: 'समिति प्रमुख',
            scName: 'एस. सी. भटनागर',
            copyright: '© 2026 भटनागर सभा गाजियाबाद (पंजी.)',
        },
        en: {
            title: 'Matrimonial Introduction Conference 2026 | Bhatnagar Sabha Ghaziabad',
            brandName: 'Bhatnagar Sabha',
            brandLocation: 'Ghaziabad (Regd.)',
            eventDetails: 'Event Details',
            invocation: 'Jai Shri Chitragupta Bhagwan',
            silverJubilee: 'Silver Jubilee Year',
            heroLineOne: '25th Kayastha',
            heroLineTwo: 'Matrimonial Introduction Conference',
            heroLead: 'A trusted platform for eligible young men, women, and their families to meet, connect, and begin new relationships.',
            dateLabel: 'Date',
            eventDate: 'Sunday, 20 December 2026',
            timeLabel: 'Time',
            eventTime: '10:00 AM – 4:00 PM',
            venueLabel: 'Venue',
            venue: 'Maharaja Agrasen Bhawan, Lohia Nagar',
            viewDetails: 'View Full Details',
            contact: 'Contact Us',
            officialInvitation: 'Official event invitation',
            familyInvited: 'Families Are Invited',
            familyDescription: 'Attending with your children improves introductions and the possibility of finalizing a suitable match.',
            deadline: 'Last Date: 15 November 2026',
            deadlineDescription: 'Please send the form and payment details by the specified date.',
            trustedEvent: 'A Trusted Event',
            trustedDescription: 'The 25th introduction conference organized by Bhatnagar Sabha Ghaziabad.',
            registrationDetails: 'Registration Details',
            paymentInformation: 'Payment Information',
            paymentInstruction: 'After payment, please include the Transaction ID, date, and screenshot with the form.',
            bankAccount: 'Bank Account',
            ifscCode: 'IFSC Code',
            accountName: 'Account Name: Bhatnagar Sabha Ghaziabad',
            upiNumber: 'UPI Number',
            upiAccepted: 'Payments are accepted through UPI',
            needHelp: 'Need Help?',
            contactOrganizers: 'Contact the Organizers',
            president: 'President',
            pramodName: 'Pramod Bhatnagar',
            generalSecretary: 'General Secretary',
            kkName: 'K. K. Bhatnagar',
            committeeHead: 'Committee Head',
            scName: 'S. C. Bhatnagar',
            copyright: '© 2026 Bhatnagar Sabha Ghaziabad (Regd.)',
        },
    };

    const buttons = document.querySelectorAll('[data-language]');

    function setLanguage(language) {
        const selected = translations[language] ? language : 'hi';
        const dictionary = translations[selected];
        document.documentElement.lang = selected;
        document.title = dictionary.title;
        document.querySelectorAll('[data-i18n]').forEach((element) => {
            const value = dictionary[element.dataset.i18n];
            if (value) element.textContent = value;
        });
        buttons.forEach((button) => {
            const active = button.dataset.language === selected;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', String(active));
        });
        localStorage.setItem('matrimonialLanguage', selected);
    }

    buttons.forEach((button) => {
        button.addEventListener('click', () => setLanguage(button.dataset.language));
    });

    setLanguage(localStorage.getItem('matrimonialLanguage') || 'hi');
})();
