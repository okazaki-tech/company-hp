# 会社 HP（WordPress テーマ + Lightsail）

Stripe 申請などで必要な会社公式サイトです。**AWS Lightsail の WordPress** で動かす想定で、**WordPress テーマ**（PHP + CSS）として実装しています。Cursor で編集し、GitHub で管理して GitHub Actions で Lightsail にデプロイできます。

## 構成

- **テーマ**: `wordpress-theme/company-hp/`（PHP + CSS）。ここを編集・デプロイします。
- ページ: ホーム、事業者について（/about）、お問い合わせ（/contact）
- 個人事業主・設立準備中であることが分かる内容です。「屋号」やメールアドレス・住所などは WordPress の「設定」や固定ページの本文で編集してください。

## 開発の流れ（ローカルで確認してから反映）

1. **Cursor** で `wordpress-theme/company-hp/` を編集
2. **ローカルで確認**（下記）してから、問題なければコミット・push
3. **Lightsail** には GitHub Actions がテーマを自動デプロイ

これにより、本番に不具合を載せずに済みます。

## ローカルでテーマを確認する

**ローカル用の WordPress は dev-platform の 1 つだけ**にしています（DB や設定の二重管理を避けるため）。

### dev-platform の shared-infra を使う（推奨）

**dev-platform** を `repositories` 直下に置き、**shared-infra** を起動すると **https://company.localhost** で WordPress が動きます。テーマはマウントされているので、Cursor で編集するとリロードで反映されます。

1. 証明書・DB・起動手順は **`docs/LIGHTSAIL_DEPLOY.md`** の「4.5 方法 C」を参照
2. **https://company.localhost** で WordPress を開き、初期設定（言語・サイト名・管理者パスワード）を行う
3. **外観** → **テーマ** で「会社HP（屋号）」を選び **有効化** する（手順の詳細は `docs/LIGHTSAIL_DEPLOY.md` の「会社 HP テーマを有効化する手順」を参照）
4. 固定ページ（ホーム・事業者について・お問い合わせ等）はテーマ有効化時に自動作成されます

**問い合わせフォームの送信テスト**をするときは、**WP Mail SMTP** で SMTP ホスト `mailpit`・ポート `1025` に設定し、送信後に **https://mailpit.localhost** でメールを確認します。証明書（mkcert）は **PowerShell で実行**してください。

### LocalWP / MAMP など（任意）

WordPress の `wp-content/themes/company-hp` に、`wordpress-theme/company-hp` をコピーまたはシンボリックリンクで配置し、テーマを有効化して確認します。メール送信テストはできません。

## Lightsail WordPress へのデプロイ

1. **Lightsail** で WordPress インスタンス（Bitnami 等）を作成し、固定 IP を付与
2. **GitHub Secrets** に `SSH_PRIVATE_KEY`, `SSH_HOST`, `SSH_USER` を設定（詳細は `docs/LIGHTSAIL_DEPLOY.md`）
3. **Repository variable**（任意）: `DEPLOY_PATH` にテーマのパスを指定
4. `main` に push すると GitHub Actions が `wordpress-theme/company-hp/` を Lightsail に転送（tar + SSH）
5. WordPress 管理画面でテーマ「会社HP（屋号）」を有効化し、固定ページ・メニューを設定

詳細: **`docs/LIGHTSAIL_DEPLOY.md`**

---

Stripe の申請では、**事業内容が分かる公式サイトの URL** を求められることがあります。Lightsail の WordPress で本テーマを有効化し、固定ページで内容を整えたうえで、その URL を申請に記載してください。
