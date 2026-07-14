<?php

namespace App\Support;

final class LegalContentPages
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function pages(): array
    {
        return [
            'terms-and-conditions' => [
                'route' => 'legal.terms',
                'label' => 'Terms and Conditions',
                'summary' => 'Platform usage rules, user obligations, and liabilities.',
                'title_key' => 'legal_terms_title',
                'content_key' => 'legal_terms_content',
                'default_title' => 'Terms and Conditions',
            ],
            'privacy-policy' => [
                'route' => 'legal.privacy',
                'label' => 'Privacy Policy',
                'summary' => 'What data is collected, why it is collected, and user rights.',
                'title_key' => 'legal_privacy_title',
                'content_key' => 'legal_privacy_content',
                'default_title' => 'Privacy Policy',
            ],
            'refund-and-cancellation-policy' => [
                'route' => 'legal.refund',
                'label' => 'Refund and Cancellation Policy',
                'summary' => 'Paid feature, subscription, cancellation, and refund terms.',
                'title_key' => 'legal_refund_title',
                'content_key' => 'legal_refund_content',
                'default_title' => 'Refund and Cancellation Policy',
            ],
            'content-policy' => [
                'route' => 'legal.content-policy',
                'label' => 'Content Policy',
                'summary' => 'Allowed/prohibited listings, moderation standards, and penalties.',
                'title_key' => 'legal_content_policy_title',
                'content_key' => 'legal_content_policy_content',
                'default_title' => 'Content Policy',
            ],
            'data-deletion-policy' => [
                'route' => 'legal.data-deletion',
                'label' => 'Account and Data Deletion Policy',
                'summary' => 'How users can delete their account and request data deletion.',
                'title_key' => 'legal_data_deletion_title',
                'content_key' => 'legal_data_deletion_content',
                'default_title' => 'Account and Data Deletion Policy',
            ],
        ];
    }

    /**
     * @return array<string, string>|null
     */
    public static function definition(string $slug): ?array
    {
        return self::pages()[$slug] ?? null;
    }

    public static function defaultContent(string $slug, string $siteName, string $supportEmail = '', string $supportPhone = ''): string
    {
        $contactLine = self::contactLine($supportEmail, $supportPhone);

        return match ($slug) {
            'terms-and-conditions' => self::termsDefault($siteName, $contactLine),
            'privacy-policy' => self::privacyDefault($siteName, $contactLine),
            'refund-and-cancellation-policy' => self::refundDefault($siteName, $contactLine),
            'content-policy' => self::contentPolicyDefault($siteName, $contactLine),
            'data-deletion-policy' => self::dataDeletionDefault($siteName, $contactLine),
            default => '',
        };
    }

    private static function contactLine(string $supportEmail, string $supportPhone): string
    {
        $parts = [];

        $normalizedEmail = trim($supportEmail);
        if ($normalizedEmail !== '') {
            $parts[] = 'Email: '.$normalizedEmail;
        }

        $normalizedPhone = trim($supportPhone);
        if ($normalizedPhone !== '') {
            $parts[] = 'Phone: '.$normalizedPhone;
        }

        if ($parts === []) {
            return 'Email: support@example.com';
        }

        return implode(' | ', $parts);
    }

    private static function termsDefault(string $siteName, string $contactLine): string
    {
        return "Last updated: March 16, 2026\n\n"
            ."Welcome to {$siteName}. These Terms and Conditions govern your use of our marketplace website, mobile web app, and related services. By creating an account, posting a listing, or using any service feature, you agree to these terms.\n\n"
            ."1. Eligibility and Account Responsibility\n"
            ."You must provide accurate information during registration and keep your account credentials secure. You are responsible for all activity performed through your account.\n\n"
            ."2. User Content and Listings\n"
            ."You are solely responsible for the listings, images, descriptions, and messages you publish. Content must be lawful, accurate, and must not infringe third-party rights.\n\n"
            ."3. Prohibited Use\n"
            ."You must not post illegal, counterfeit, stolen, unsafe, misleading, or fraudulent content. Spam, harassment, impersonation, and attempts to bypass moderation or payment controls are strictly prohibited.\n\n"
            ."4. Payments and Paid Features\n"
            ."Paid features such as featured ads, subscriptions, and promotional tools are billed as displayed at checkout. Fees may be updated in the future with prior notice in the app or on the website.\n\n"
            ."5. Moderation and Enforcement\n"
            ."We may review, limit, unpublish, or remove content, and suspend or terminate accounts that violate platform rules or applicable law.\n\n"
            ."6. Third-Party Services\n"
            ."Some features may use third-party services (for example, payment gateways, push delivery, analytics, maps). Their use may be subject to separate third-party terms and privacy notices.\n\n"
            ."7. Limitation of Liability\n"
            ."{$siteName} acts as a platform and is not a party to transactions between buyers and sellers. To the maximum extent permitted by law, we are not liable for indirect, incidental, or consequential losses arising from platform use.\n\n"
            ."8. Changes to Terms\n"
            ."We may revise these terms to reflect legal, operational, or product changes. Updated terms become effective when posted on this page.\n\n"
            ."9. Contact\n"
            ."For legal or compliance questions regarding these terms, contact us at: {$contactLine}.\n";
    }

    private static function privacyDefault(string $siteName, string $contactLine): string
    {
        return "Last updated: March 16, 2026\n\n"
            ."This Privacy Policy explains how {$siteName} collects, uses, stores, and protects your information when you use our marketplace services.\n\n"
            ."1. Information We Collect\n"
            ."We may collect account details (name, email, phone), profile information, listing content, uploaded media, transaction metadata, support communications, and technical data such as device, browser, and usage logs.\n\n"
            ."2. Location and Permissions\n"
            ."If you grant location permission, we use approximate or precise location data to improve nearby listings, maps, and local discovery features. You can disable location permission in device settings.\n\n"
            ."3. How We Use Data\n"
            ."We use collected data to operate the marketplace, verify accounts, prevent abuse/fraud, personalize feeds, deliver notifications, process payments, provide customer support, and comply with legal obligations.\n\n"
            ."4. Data Sharing\n"
            ."We do not sell personal data. We may share limited data with trusted processors (hosting, analytics, communication, payment, and compliance partners) only for service delivery and legal requirements.\n\n"
            ."5. Retention\n"
            ."We retain data only as long as needed for service operations, dispute handling, fraud prevention, and legal compliance. Retention period depends on the data type and applicable law.\n\n"
            ."6. Security\n"
            ."We apply reasonable administrative, technical, and organizational safeguards to protect user data. No online system is 100% secure, but we continuously improve controls.\n\n"
            ."7. Your Rights\n"
            ."Depending on your jurisdiction, you may request access, correction, export, or deletion of personal data. You may also withdraw certain permissions from app/browser settings.\n\n"
            ."8. Child Safety\n"
            ."Our services are not directed to children under the minimum lawful age in your region. If we become aware of unauthorized child data, we will take appropriate action.\n\n"
            ."9. Policy Updates\n"
            ."We may update this policy from time to time. The latest version will always be published on this page with an updated effective date.\n\n"
            ."10. Contact\n"
            ."For privacy queries or data requests, contact us at: {$contactLine}.\n";
    }

    private static function refundDefault(string $siteName, string $contactLine): string
    {
        return "Last updated: March 16, 2026\n\n"
            ."This Refund and Cancellation Policy applies to paid marketplace features offered by {$siteName}, including highlighted listings, paid boosts, and subscription packages.\n\n"
            ."1. Nature of Digital Services\n"
            ."Most paid features are digital and begin processing immediately after successful payment. Once a feature is consumed or activated, it is generally non-refundable.\n\n"
            ."2. Eligible Refund Cases\n"
            ."Refunds may be considered if:\n"
            ."- duplicate or accidental double charge occurred for the same order\n"
            ."- payment was completed but service was not delivered due to a verified technical failure\n"
            ."- law requires a refund in your jurisdiction\n\n"
            ."3. Non-Refundable Cases\n"
            ."Refunds are normally not provided for consumed ad boosts, expired feature windows, policy-based listing removals, account restrictions for violations, or user mistakes in listing details.\n\n"
            ."4. Cancellation\n"
            ."If subscriptions are offered, cancellation stops future renewal where applicable. Cancellation does not automatically create refunds for already billed periods unless legally required.\n\n"
            ."5. Processing Time\n"
            ."Approved refunds are processed to the original payment method. Processing time depends on banking and gateway networks, usually within 5 to 10 business days.\n\n"
            ."6. How to Request\n"
            ."Share your account email/phone, order reference, payment date, and issue details for review.\n"
            ."Contact: {$contactLine}.\n";
    }

    private static function contentPolicyDefault(string $siteName, string $contactLine): string
    {
        return "Last updated: March 16, 2026\n\n"
            ."{$siteName} is committed to a safe and trustworthy marketplace. This Content Policy defines what is allowed and what is prohibited.\n\n"
            ."1. Allowed Content\n"
            ."Listings and user content must be lawful, accurate, and relevant to the selected category, with genuine images and clear pricing/condition information.\n\n"
            ."2. Prohibited Content\n"
            ."The following are prohibited:\n"
            ."- illegal goods/services or regulated items without proper authorization\n"
            ."- counterfeit, stolen, or misleading products\n"
            ."- adult exploitation, hate, violent extremism, or harassment\n"
            ."- scam attempts, phishing, deceptive payment requests, or impersonation\n"
            ."- malware links, spam campaigns, or repeated duplicate postings\n\n"
            ."3. Enforcement\n"
            ."Violations may result in content takedown, reduced visibility, temporary restrictions, permanent suspension, and where necessary, legal escalation.\n\n"
            ."4. Reporting and Appeals\n"
            ."Users can report suspicious listings/messages. Moderation decisions may be reviewed when valid evidence is submitted through support channels.\n\n"
            ."5. Safety Guidance\n"
            ."Prefer in-app communication, verify seller/buyer details, avoid off-platform payment requests, and use secure payment workflows whenever available.\n\n"
            ."6. Contact\n"
            ."For moderation or safety appeals, contact: {$contactLine}.\n";
    }

    private static function dataDeletionDefault(string $siteName, string $contactLine): string
    {
        return "Last updated: March 16, 2026\n\n"
            ."This Account and Data Deletion Policy explains how users of {$siteName} can request account closure and personal data deletion.\n\n"
            ."1. Self-Service Account Deletion\n"
            ."Logged-in users can delete their account from the profile settings section. This action is irreversible after completion.\n\n"
            ."2. What Happens After Deletion\n"
            ."When an account is deleted, we disable access and begin removal or anonymization of personal profile data and linked listing metadata, subject to legal and security retention requirements.\n\n"
            ."3. Retained Data\n"
            ."Certain records may be retained for a limited period for legal compliance, fraud prevention, dispute resolution, financial auditing, and enforcement of platform integrity.\n\n"
            ."4. Third-Party Processors\n"
            ."Where applicable, deletion requests are propagated to relevant processors that store data on our behalf, according to contractual and legal timelines.\n\n"
            ."5. Requesting Manual Deletion Support\n"
            ."If you cannot access your account, contact support with your registered email/phone and verification details to request deletion assistance.\n"
            ."Contact: {$contactLine}.\n";
    }
}
