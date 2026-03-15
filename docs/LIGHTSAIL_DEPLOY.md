# AWS Lightsail で HP を運用する（WordPress テーマ + Cursor + GitHub）

Lightsail の **WordPress** で HP を運用する想定です。スタイルやテンプレートを Cursor で編集し、GitHub で管理して、GitHub Actions で WordPress テーマとしてデプロイします。

---

## 1. 構成の概要

| 項目 | 内容 |
|------|------|
| サイトの土台 | Lightsail の WordPress（**WordPress certified by Bitnami** を推奨） |
| 見た目・レイアウト | カスタム WordPress テーマ（`wordpress-theme/company-hp/`） |
| 編集場所 | Cursor（PC 上で PHP / CSS を編集） |
| バージョン管理 | GitHub |
| デプロイ | GitHub Actions でテーマフォルダを Lightsail に転送（tar + SSH） |

**React は使いません**。Lightsail の WordPress は PHP ベースのため、同じデザインを PHP テーマで再現しています。

---

## 2. 開発の流れ（ローカルで確認してから反映）

| ステップ | 内容 |
|---------|------|
| 1. ローカルで編集 | Cursor で `wordpress-theme/company-hp/` 内の PHP・CSS を編集 |
| 2. **ローカルで確認** | ローカルの WordPress でテーマを有効化し、表示・動作を確認（**「4.5 ローカル環境での確認」** 参照） |
| 3. 問題なければ push | 確認できたらコミット・プッシュ |
| 4. デプロイ | GitHub Actions がテーマフォルダを Lightsail の WordPress に転送（tar + SSH） |

**ローカルでしっかり確認してから push することで、Lightsail には問題のない変更だけが反映されます。** ビルドは不要です（PHP と CSS をそのままアップロード）。

---

## 3. Lightsail インスタンスの作成（詳細手順）

### 3.1 インスタンスの作成

1. **AWS コンソール** にログインし、**Lightsail** を開く  
   - https://lightsail.aws.amazon.com/

2. **「インスタンスの作成」** をクリック

3. **「Apps + OS」** を選択し、次のいずれかを選ぶ  
   - **推奨**: **「WordPress certified by Bitnami」**  
   - 本ドキュメントのパス（`/opt/bitnami/wordpress/` など）は Bitnami 版を前提にしています。  
   - 「WordPress」（Lightsail パッケージ版）を選んだ場合は、後でテーマの配置パスを確認し、GitHub の `DEPLOY_PATH` を合わせてください。

4. **インスタンス名** を入力（例: `company-hp-wordpress`）

5. **リージョン** を選択（例: 東京 `ap-northeast-1`）

6. **プラン** を選択  
   - 初期は $3.50/月 や $5/月 などで開始し、必要に応じてあとから変更可能です。

7. **SSH キーペア**  
   - **「デフォルト」** のままにするか、あらかじめ Lightsail の「アカウント」→「SSH キー」で作成したキーを選択します。  
   - デプロイ用に使う秘密鍵は、ここで選んだキーペアの **秘密鍵（.pem）** を後で GitHub Secrets に登録します。  
   - 新規キーを作る場合: 「アカウント」→「SSH キー」→「デフォルトキーの作成」または「カスタムキーの作成」で作成し、**秘密鍵（.pem）をダウンロードして安全に保管**してください。

8. **「インスタンスの作成」** をクリックして作成を開始

### 3.2 固定 IP（静的 IP）のアタッチ

GitHub Actions から同じ IP で SSH するため、**固定 IP をアタッチ**します。

1. Lightsail の左メニューで **「ネットワーク」** を選択
2. **「静的 IP の作成」** をクリック
3. **リージョン** を、作成したインスタンスと同じリージョンに
4. **「インスタンスにアタッチ」** で、作成した WordPress インスタンスを選択
5. **名前** を入力（例: `company-hp-static-ip`）
6. **「作成」** をクリック

作成後、インスタンス一覧の「パブリック IP」が静的 IP に変わります。この **IP アドレス** をメモし、後で GitHub の `SSH_HOST` に設定します。

### 3.3 初回アクセス（WordPress 管理画面・SSH）

- **サイト**: ブラウザで `http://<パブリックIP>/` または `http://<パブリックIP>/wp-admin` を開く

- **WordPress 管理画面（wp-admin）にログインするとき**
  - **ユーザー名**: **`user`**（Bitnami のデフォルト管理者。**`bitnami` ではない**）
  - **パスワード**: Lightsail コンソールでインスタンスの **「ターミナル」**（電球アイコン）を開き、次のコマンドで表示されます。  
    ```bash
    cat $HOME/bitnami_application_password
    ```
  - 「The username bitnami is not registered on this site」と出る場合は、ユーザー名を **`user`** に変更して再試行してください。

- **SSH（サーバーへのログイン）**
  - 同じ「ターミナル」からブラウザ経由で接続できます。**サーバー側のユーザー名**は **`bitnami`** です（WordPress のログインとは別です）。

---

## 4. SSH の設定（GitHub Actions デプロイ用）

### 4.1 使う鍵の確認

- インスタンス作成時に選んだ **SSH キーペア** の **秘密鍵（.pem）** を使います。
- Lightsail の **「アカウント」→「SSH キー」** で、該当リージョンのキーを選び、**「ダウンロード」** すると秘密鍵が取得できます（作成時しかダウンロードできない場合もあるため、既に保存していない場合は新規キーを作成してインスタンスに紐づけ直す必要があります）。

### 4.2 秘密鍵を GitHub Secrets に登録

1. リポジトリの **Settings** → **Secrets and variables** → **Actions**
2. **New repository secret** で以下を追加します。

| Secret 名 | 内容 |
|-----------|------|
| `SSH_PRIVATE_KEY` | 秘密鍵（.pem）の **中身全体**（`-----BEGIN ... KEY-----` から `-----END ... KEY-----` まで）をコピー＆ペースト。改行も含めてそのまま。 |
| `SSH_HOST` | Lightsail の **静的 IP アドレス**（例: `3.112.23.45`） |
| `SSH_USER` | **`bitnami`**（Bitnami WordPress のデフォルト SSH ユーザー） |

### 4.3 ローカルから SSH 接続する場合（任意）

秘密鍵を手元に保存している場合の例です。

```bash
# 秘密鍵の権限を設定
chmod 600 /path/to/your-key.pem

# 接続（bitnami ユーザー、Lightsail の静的 IP）
ssh -i /path/to/your-key.pem bitnami@<静的IP>
```

接続できれば、GitHub Actions からも同じ鍵・ユーザー・IP で接続できる前提になります。

### 4.4 テーマ用ディレクトリの権限（必要な場合）

初回デプロイで `company-hp` フォルダが自動作成されます。権限エラーになる場合は、SSH でサーバーにログインし、次を実行してください。

```bash
sudo mkdir -p /opt/bitnami/wordpress/wp-content/themes/company-hp
sudo chown bitnami:daemon /opt/bitnami/wordpress/wp-content/themes/company-hp
```

### 4.5 ローカル環境での確認（push 前に実施）

Lightsail に反映する前に、**ローカルで WordPress を動かしてテーマの表示・動作を確認**します。確認できた変更だけを push することで、本番に不具合を載せずに済みます。

#### 方法 A: dev-platform の shared-infra でローカル WordPress を動かす（推奨・1 環境のみ）

ローカル用の WordPress は **dev-platform の https://company.localhost のみ**に統一しています（DB や設定の重複を避けるため。company-hp 単体の Docker は廃止済み）。

**起動の流れ:** dev-platform を `repositories` 直下に置く → 証明書（mkcert）・WordPress 用 DB 作成・`docker compose up -d` は **方法 C** の手順に従う → **https://company.localhost** を開き、WordPress の初期設定（言語・サイト名・管理者パスワード）を行う → **会社 HP テーマを有効化**（下記手順）。固定ページはテーマ有効化時に自動作成されます。

**会社 HP テーマを有効化する手順**（ローカル・Lightsail 共通）
1. WordPress 管理画面にログインする（例: https://company.localhost/wp-admin または Lightsail のサイト URL/wp-admin）
2. 左メニューで **「外観」** → **「テーマ」** をクリック
3. テーマ一覧から **「会社HP（屋号）」** を探し、**「有効化」** をクリック
4. 有効化後、トップや固定ページが当テーマのデザインで表示されます。固定ページ（ホーム・事業者について・お問い合わせ等）は自動作成されます。

**トップ以外のページを確認するには**

テーマを有効化するか、デプロイ後に管理画面を一度開くと、**固定ページ（ホーム・事業者について・お問い合わせ・プライバシーポリシー・利用規約）が自動作成**されます（詳細は本ドキュメント「8. テーマのページ構成」）。手動で作る場合は以下です。

1. 管理画面 **https://company.localhost/wp-admin** にログイン
2. **固定ページ** → **新規追加** で、次のページを必要な分だけ作成する（タイトルとスラッグを下表のとおりに）。スラッグの変更方法は下記「スラッグの変更方法」参照。
   | 表示したいページ | タイトル（任意） | スラッグ（必須） |
   |------------------|------------------|------------------|
   | 事業者について   | 事業者について   | `about`          |
   | お問い合わせ     | お問い合わせ     | `contact`        |
   | プライバシーポリシー | プライバシーポリシー | `privacy-policy` |
   | 利用規約         | 利用規約         | `terms`          |
3. **スラッグの変更方法（ブロックエディタ・最新版）**
   - **前提**: **設定** → **パーマリンク** で、共通設定を **「投稿名」**（`/%postname%/`）にしておく。「基本」のままだとスラッグは URL に反映されません。
   - **方法 A（編集画面の右サイドバー）**
     1. **固定ページ** 一覧で、対象ページの **「編集」** をクリックしてブロックエディタを開く
     2. 右側のサイドバーを開く。出ていない場合は画面右上の **「設定」**（歯車アイコン）をクリック
     3. サイドバー上部で **「ページ」** タブ（または「投稿」タブ）が選ばれていることを確認
     4. 下にスクロールし、**「概要」** または **「Summary」** のブロック内を探す
     5. **「URL」** と書かれた行に、現在の URL（例: `https://example.com/○○/`）が表示されているので、**その URL 自体をクリック**する
     6. クリックするとスラッグを編集できる入力欄（ポップオーバーまたはインライン）が現れる。`about` など**半角英数字とハイフン**で入力
     7. Enter キーを押すか、**「×」** で閉じて確定
     8. 画面右上の **「更新」** をクリックして保存
   - **方法 B（クイック編集）**
     1. **固定ページ** 一覧を開く
     2. 対象ページにマウスを乗せ、表示される **「クイック編集」** をクリック
     3. **「スラッグ」** 欄に `about` などを入力し、**「更新」** をクリック
   - スラッグは半角英数字とハイフン（`-`）のみにすると安全です。日本語は避けてください。
4. 各ページの URL で直接開いて表示を確認する（例: **https://company.localhost/about/**）
5. **外観** → **メニュー** で「メインメニュー」に上記ページを追加すると、ヘッダーからトップ以外のページに遷移して確認できます。

#### 方法 B: LocalWP や MAMP など既存のローカル WordPress を使う

1. LocalWP（https://localwp.com/）や MAMP などで WordPress をインストール・起動
2. テーマフォルダを配置  
   - **コピー**: `wordpress-theme/company-hp` を `wp-content/themes/company-hp` にコピー  
   - **シンボリックリンク**（編集がそのまま反映される）:  
     ```bash
     ln -s <リポジトリのパス>/company-hp/wordpress-theme/company-hp <WordPressのパス>/wp-content/themes/company-hp
     ```
3. 管理画面で「会社HP（屋号）」テーマを有効化し、表示を確認
4. 問題なければ Cursor でコミット・push

#### 方法 C: 問い合わせフォームの送信テスト（Mailpit）

**方法 A** で https://company.localhost を動かしている前提で、**問い合わせフォームの送信〜メール受信**を確認するには、**Mailpit** に送信先を向けます。

1. **dev-platform** を `repositories` 直下に置き、**company-hp** と同階層にする
2. **証明書** に `company.localhost` を含めて発行（[dev-platform/shared-infra/certs/README.md](../../dev-platform/shared-infra/certs/README.md) 参照）。**Windows のブラウザで開く場合は mkcert を PowerShell で実行**してください。
3. **初回のみ** WordPress 用 DB を作成  
   ```bash
   cd dev-platform/shared-infra
   docker exec -i $(docker ps -qf 'name=mysql' | head -1) mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS wordpress;"
   ```
4. **shared-infra を起動**（Mailpit もここで起動します）  
   ```bash
   cd dev-platform/shared-infra
   docker compose up -d
   ```
5. ブラウザで **https://company.localhost** を開き、WordPress の初期設定（言語・サイト名・管理者パスワード）を行う
6. **会社 HP テーマを有効化**（上記「会社 HP テーマを有効化する手順」参照）
7. **Contact Form 7** と **WP Mail SMTP** をインストール・有効化し、お問い合わせページにフォームを設置
7. **WP Mail SMTP** で送信先を **Mailpit** に設定する（メールは送信されず Mailpit が受け取ります）
   | 項目 | 値 |
   |------|-----|
   | メール送信方法 | 「その他のSMTP」を選択 |
   | SMTP ホスト | `mailpit` |
   | SMTP ポート | `1025` |
   | 暗号化 | なし |
   | 認証 | オフ（Mailpit は認証不要） |
9. お問い合わせフォームから送信し、**https://mailpit.localhost** を開いてメールが届いているか確認

**補足:** Mailpit は shared-infra の Docker ネットワーク内で `mailpit` というホスト名で動いています。WordPress コンテナからは `mailpit:1025` で SMTP 接続できます。メールの Web UI は **https://mailpit.localhost** です。

**company.localhost が HTTP のまま・「保護されていない通信」になる場合**

HTTPS で開くには **mkcert で証明書を発行**し、Traefik が読み込めるようにする必要があります。

1. **mkcert をインストール**（未導入の場合）  
   [dev-platform/shared-infra/certs/README.md](../../dev-platform/shared-infra/certs/README.md) の「1. mkcert をインストール」を参照。
2. **証明書を発行**（`company.localhost` を必ず含める）  
   **Windows のブラウザで開く場合は PowerShell で実行**してください（WSL で発行すると Windows のブラウザが信頼しない場合があります）。
   - **PowerShell（WSL 内のリポジトリにアクセスする例）**
     ```powershell
     cd \\wsl$\Ubuntu\home\<あなたのユーザー名>\repositories\dev-platform\shared-infra\certs
     mkcert -cert-file cert.pem -key-file key.pem traefik.localhost mailpit.localhost minio.localhost tools.localhost company.localhost iq-test.localhost
     ```
   - **WSL / Linux**（WSL 内のブラウザのみで開く場合）
     ```bash
     cd dev-platform/shared-infra/certs
     mkcert -cert-file cert.pem -key-file key.pem traefik.localhost mailpit.localhost minio.localhost tools.localhost company.localhost iq-test.localhost
     ```
3. **shared-infra を再起動**
   ```bash
   cd dev-platform/shared-infra
   docker compose down && docker compose up -d
   ```
4. ブラウザで **https://company.localhost** を開く（`http://` ではなく **https://** でアクセス）。

詳細は **dev-platform の [shared-infra/certs/README.md](../../dev-platform/shared-infra/certs/README.md)** および **dev-platform の README「5. 会社 HP（company-hp）を dev-platform で動かす」** を参照してください。

---

## 5. GitHub 側の設定（Repository variables・任意）

| Variable 名 | 内容 |
|-------------|------|
| `DEPLOY_PATH` | テーマの転送先。Bitnami の場合は未設定でよい（デフォルト: `/opt/bitnami/wordpress/wp-content/themes/company-hp`）。別の WordPress 構成の場合はここでパスを指定。 |

---

## 6. リポジトリ・ワークフロー

- `.github/workflows/deploy.yml` が **WordPress テーマ** をデプロイします。
- `main` へ push（または手動で workflow 実行）すると、`wordpress-theme/company-hp/` の中身が `DEPLOY_PATH` に転送されます（リモートに rsync が無いため tar + SSH で転送）。
- デプロイ後、**会社 HP テーマを有効化**してください（手順は「4.5 ローカル環境での確認」内の「会社 HP テーマを有効化する手順」を参照）。

---

## 7. スタイルのカスタマイズ

- **Cursor** で `wordpress-theme/company-hp/style.css` や `*.php` を編集
- 変更をコミットして push すると、GitHub Actions がテーマを再デプロイ
- サーバー上では編集しない（常にリポジトリを正とする）

---

## 8. テーマのページ構成

- **トップ**: `front-page.php`（ヒーロー + サービス・事業内容）
- **固定ページの自動作成**: テーマを有効化したとき、またはデプロイ後に管理画面を開いたとき、次の固定ページが**無ければ自動作成**されます。画面上での手動作成は原則不要です。
  - ホーム（スラッグ: `home`）… 表示設定で「ホームページ」に未設定なら、ここをトップに設定
  - 事業者について（`about`）
  - お問い合わせ（`contact`）
  - プライバシーポリシー（`privacy-policy`）
  - 利用規約（`terms`）
- 既に同じスラッグのページがある場合は作成されません。必要なら **外観** → **メニュー** で「メインメニュー」に各ページを追加してください。

---

## 9. Contact Form 7 の設定（Lightsail WordPress）

### 9.1 インストールと有効化

1. WordPress 管理画面にログイン
2. **プラグイン** → **新規追加**
3. 「Contact Form 7」で検索し、**今すぐインストール** → **有効化**

### 9.2 フォームをお問い合わせページに表示

1. **お問い合わせ** の固定ページを編集（自動作成されていれば「お問い合わせ」）
2. **Contact Form 7** → **連絡先** で、デフォルトの「お問い合わせ」フォームの**ショートコード**をコピー（例: `[contact-form-7 id="123" title="お問い合わせ"]`）
3. 固定ページの本文にそのショートコードを貼り付けて **更新**

これでお問い合わせページにフォームが表示されます。

### 9.3 メール設定（宛先・件名・本文）

1. **Contact Form 7** → **連絡先** で該当フォームの **編集** をクリック
2. **メール** タブを開く
   - **宛先** … 届け先のメールアドレス（例: 事業用アドレス）
   - **送信元** … 送信元として表示するアドレス（例: `[your-name] <wordpress@あなたのドメイン>` または固定アドレス）
   - **件名** … 例: `【サイト名】お問い合わせ - [your-subject]`
   - **本文** … 送信内容（`[your-name]` や `[your-email]` などのタグでフォームの値を挿入可能）
3. **メール (2)** は「送信者に写しを送る」用。必要なら有効化して設定
4. **保存**

### 9.3.1 ローカル（company.localhost）で送信を成功させる

**https://company.localhost/contact/** で「メッセージの送信に失敗しました」となる場合、WordPress のメール送信を **Mailpit** に向ける設定が必要です。Contact Form 7 だけでは送信できません。

1. **WP Mail SMTP をインストール**
   - **プラグイン** → **新規追加** → 「WP Mail SMTP」で検索 → **今すぐインストール** → **有効化**
2. **Mailpit に送る設定**
   - **WP Mail SMTP** → **設定**（または **メール**）を開く
   - **メール送信方法**: **その他の SMTP** を選択
   - **SMTP ホスト**: **`mailpit`**（小文字・余分なスペースなし）
   - **SMTP ポート**: **1025**
   - **暗号化**: **なし**
   - **認証**: **オフ**（ユーザー名・パスワードは空のまま）
   - **変更を保存**
3. **動作確認**
   - WP Mail SMTP の「テストメールを送信」で送信し、エラーが出ないか確認
   - **https://mailpit.localhost** を開き、受信トレイにメールが届いているか確認
4. **dev-platform が起動しているか**
   - `cd dev-platform/shared-infra && docker compose ps` で `mailpit` と `company-hp-web` が **Up** になっていることを確認

これでお問い合わせフォームから送信すると、メールが Mailpit に届き、https://mailpit.localhost で内容を確認できます。

**「SPF レコードを追加してください」という表示について**

- **dev-platform / shared-infra で SPF を設定することはできません**。SPF は「送信元ドメイン」の**公開 DNS**（お名前.com・Route 53・Cloudflare など）に追加する TXT レコードであり、ローカルの Docker 環境には存在しないドメインの DNS を変更するものではありません。
- ローカルで Mailpit だけを使っている場合は **無視して問題ありません**。Mailpit はローカル内でメールをキャッチするだけなので、SPF は不要です。本番で Amazon SES や Gmail などを使うときに、その SMTP 事業者の案内に従い、**本番ドメインの DNS** に SPF（や DKIM）を追加してください。
- ローカル開発中に SPF の案内を非表示にしたい場合は、**WP Mail SMTP** → **設定** → **その他（Misc）** タブで **「Hide Announcements」**（お知らせを非表示）を有効にすると、SPF の案内を含むプラグインのお知らせが表示されなくなります。

### 9.4 メールが届かない場合（Lightsail で重要）

Lightsail のサーバーでは **PHP の mail() が使えない・届きにくい** ことがあります。その場合は **SMTP プラグイン** で外部 SMTP 経由にします。

**推奨手順**

1. **WP Mail SMTP** や **FluentSMTP** などの SMTP プラグインをインストール・有効化
2. 以下のいずれかで「送信方法」を設定する  
   - **Amazon SES** … AWS の同じリージョンで SES を有効化し、SMTP 認証情報をプラグインに入力（Lightsail と相性が良い）  
   - **Gmail / その他 SMTP** … 送信専用アドレスとアプリパスワード、または SMTP サーバー・ポート（587 など）を設定
3. プラグインの「テスト送信」で届くか確認してから、Contact Form 7 で実際に送信テスト

**注意**

- Lightsail ではポート 25 が制限されている場合があるため、**ポート 587（SMTP over TLS）** を使う SMTP サービスを選ぶとよいです。
- ドメインのメールアドレス（例: `info@example.com`）で送る場合は、SES なら「ドメインの認証」、他の SMTP ならそのサービスの設定に従ってください。
- **SPF / DKIM**: 本番で外部 SMTP（SES・Gmail など）を使う場合、WP Mail SMTP などから「SPF レコードを追加してください」と表示されることがあります。届いたメールが迷惑メール扱いにならないよう、使用している SMTP 事業者の案内に従い、**ドメインの DNS** に SPF（および DKIM）レコードを追加してください。レコードの内容や追加方法は事業者ごとに異なるため、各サービスのドキュメントを参照してください。

### 9.5 フォーム項目のカスタマイズ

**Contact Form 7** → **連絡先** → 該当フォームの **編集** で「フォーム」タブを開き、タグを追加・編集できます。例: 会社名、電話番号、お問い合わせ種別など。挿入できるタグ一覧は画面上の「メールタグ」で確認できます。

---

## 10. トラブルシューティング

### 「Error establishing a database connection」（company.localhost）

dev-platform で **https://company.localhost** を開いたときにこのエラーが出る場合、**WordPress 用のデータベースがまだない**ことがほとんどです。

1. **shared-infra が起動しているか確認**
   ```bash
   cd dev-platform/shared-infra
   docker compose ps
   ```
   `mysql` と `company-hp-web` が Up になっていることを確認。

2. **WordPress 用 DB を作成**
   ```bash
   cd dev-platform/shared-infra
   docker exec -i $(docker ps -qf 'name=mysql' | head -1) mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS wordpress;"
   ```
   （コンテナ名が `mysql` でない場合は `docker ps` で MySQL のコンテナ名を確認し、`docker exec -i <コンテナ名> mysql ...` に置き換える。）

3. **company-hp-web を再起動**（必要なら）
   ```bash
   docker compose restart company-hp-web
   ```

4. ブラウザで **https://company.localhost** を再読み込み。初回は WordPress の初期設定画面（言語・サイト名・管理者パスワード）が表示されます。

### 「/contact/ などが 404 Not Found になる場合」

固定ページ（お問い合わせ・事業者についてなど）の URL が 404 になる場合は、**パーマリンク（リライトルール）が未反映**の可能性があります。

1. WordPress 管理画面にログインし、**設定** → **パーマリンク** を開く
2. 共通設定で **「投稿名」**（`/%postname%/`）を選択
3. 変更がなくても **「変更を保存」** をクリックする（リライトルールが再生成されます）
4. 再度 **https://company.localhost/contact/** などにアクセスして確認

※ テーマで自動作成した固定ページは、作成時にリライトルールを更新する処理を入れています。初回セットアップ直後や、パーマリンクを「基本」のままにしている場合は上記の手順で解消します。

### 「メッセージの送信に失敗しました。後でまたお試しください。」（Contact Form 7）

**ローカル（company.localhost）の場合**

→ **「9.3.1 ローカル（company.localhost）で送信を成功させる」** の手順に従い、**WP Mail SMTP** をインストールして **Mailpit**（ホスト名 `mailpit`、ポート `1025`）に送る設定にしてください。設定後、テスト送信と https://mailpit.localhost での受信を確認してください。

**本番（Lightsail）の場合**

- サーバーの PHP `mail()` は使えないことが多いため、**WP Mail SMTP** で **Amazon SES** や **Gmail など外部 SMTP** を設定する。  
- 「9. Contact Form 7 の設定」の「9.4 メールが届かない場合」を参照。

### SSH で "Permission denied (publickey)"

- `SSH_PRIVATE_KEY` に秘密鍵の **全文**（ヘッダ・フッタ含む、改行もそのまま）が入っているか確認
- `SSH_USER` が **`bitnami`** になっているか確認
- `SSH_HOST` に **静的 IP** を指定しているか確認（インスタンス再起動で変わる通常のパブリック IP だと、再起動のたびに変わります）
- Lightsail の「アカウント」→「SSH キー」で、該当リージョンにキーが存在し、インスタンス作成時にそのキーを選択しているか確認

### デプロイで "Permission denied" や "No such file or directory"

- テーマ用ディレクトリを手動で作成し、`bitnami` が書き込めるようにする（「4.4 テーマ用ディレクトリの権限」を参照）
- `DEPLOY_PATH` が実際の Bitnami のパス（`/opt/bitnami/wordpress/wp-content/themes/company-hp`）と一致しているか確認

---

## 11. 参考

- [Deploy and manage WordPress on Lightsail（AWS）](https://docs.aws.amazon.com/lightsail/latest/userguide/amazon-lightsail-quick-start-guide-wordpress.html)
- [GitHub Actions で VPS（Lightsail）にデプロイ](https://shugomatsuzawa.com/techblog/2024/03/13/358/)
- [AWS Lightsail + GitHub Actions で低コスト Web アプリインフラ構築](https://qiita.com/aosan/items/e0380933540b10d22fee)
