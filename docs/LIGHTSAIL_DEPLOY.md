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
| デプロイ | GitHub Actions でテーマフォルダを Lightsail に rsync |

**React は使いません**。Lightsail の WordPress は PHP ベースのため、同じデザインを PHP テーマで再現しています。

---

## 2. 開発の流れ（ローカルで確認してから反映）

| ステップ | 内容 |
|---------|------|
| 1. ローカルで編集 | Cursor で `wordpress-theme/company-hp/` 内の PHP・CSS を編集 |
| 2. **ローカルで確認** | ローカルの WordPress でテーマを有効化し、表示・動作を確認（**「4.5 ローカル環境での確認」** 参照） |
| 3. 問題なければ push | 確認できたらコミット・プッシュ |
| 4. デプロイ | GitHub Actions がテーマフォルダを Lightsail の WordPress に rsync |

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

#### 方法 A: Docker でローカル WordPress を起動（推奨）

リポジトリに `docker-compose.yml` を用意しています。テーマフォルダをコンテナ内にマウントするため、**Cursor で編集するとそのままブラウザで反映**されます。

```bash
cd <リポジトリのパス>/company-hp
docker compose up -d
```

初回のみ、ブラウザで http://localhost:8080 を開き、WordPress の初期設定（言語・サイト名・管理者ユーザー・パスワード）を行ってください。  
設定後、**外観 → テーマ** で「会社HP（屋号）」を有効化し、トップや固定ページの表示・スタイルを確認します。編集したらブラウザをリロードして確認し、問題なければコミット・push して Lightsail にデプロイします。

終了するときは `docker compose down` で停止できます。

**トップ以外のページを確認するには**

テーマを有効化するか、デプロイ後に管理画面を一度開くと、**固定ページ（ホーム・事業者について・お問い合わせ・プライバシーポリシー・利用規約）が自動作成**されます（詳細は本ドキュメント「8. テーマのページ構成」）。手動で作る場合は以下です。

1. 管理画面（**http://localhost:8080/wp-admin** または **https://company.localhost/wp-admin**）にログイン
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
4. 各ページの URL で直接開いて表示を確認する  
   - Docker: **http://localhost:8080/about/** など  
   - dev-platform: **https://company.localhost/about/** など  
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

#### 方法 C: dev-platform で問い合わせフォームを確認する

**問い合わせフォームの送信〜メール受信**までまとめて確認したい場合は、**dev-platform** を利用します。Traefik で **https://company.localhost** に WordPress が立ち、送信メールは **Mailpit（https://mailpit.localhost）** で確認できます。

1. **dev-platform** を `repositories` 直下に置き、**company-hp** と同階層にする
2. **証明書** に `company.localhost` を含めて発行（[dev-platform/shared-infra/certs/README.md](../../dev-platform/shared-infra/certs/README.md) 参照）。**Windows のブラウザで開く場合は mkcert を PowerShell で実行**してください。
3. **初回のみ** WordPress 用 DB を作成  
   ```bash
   cd dev-platform/shared-infra
   docker exec -i $(docker ps -qf 'name=mysql' | head -1) mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS wordpress;"
   ```
4. **共有インフラを起動**  
   ```bash
   docker compose up -d
   ```
5. ブラウザで **https://company.localhost** を開き、WordPress の初期設定後、テーマ「会社HP（屋号）」を有効化
6. **問い合わせフォーム**（Contact Form 7 等）を設置し、**WP Mail SMTP** 等で送信先を **Mailpit** に設定（SMTP ホスト: `mailpit`、ポート: `1025`、暗号化: なし）
7. フォーム送信後、**https://mailpit.localhost** でメールが届いているか確認

詳細は **dev-platform の README「5. 会社 HP（company-hp）を dev-platform で動かす」** を参照してください。

---

## 5. GitHub 側の設定（Repository variables・任意）

| Variable 名 | 内容 |
|-------------|------|
| `DEPLOY_PATH` | テーマの転送先。Bitnami の場合は未設定でよい（デフォルト: `/opt/bitnami/wordpress/wp-content/themes/company-hp`）。別の WordPress 構成の場合はここでパスを指定。 |

---

## 6. リポジトリ・ワークフロー

- `.github/workflows/deploy.yml` が **WordPress テーマ** をデプロイします。
- `main` へ push（または手動で workflow 実行）すると、`wordpress-theme/company-hp/` の中身が `DEPLOY_PATH` に rsync されます。
- デプロイ後、WordPress 管理画面 → **外観** → **テーマ** で「会社HP（屋号）」を有効化してください。

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

## 9. トラブルシューティング

### SSH で "Permission denied (publickey)"

- `SSH_PRIVATE_KEY` に秘密鍵の **全文**（ヘッダ・フッタ含む、改行もそのまま）が入っているか確認
- `SSH_USER` が **`bitnami`** になっているか確認
- `SSH_HOST` に **静的 IP** を指定しているか確認（インスタンス再起動で変わる通常のパブリック IP だと、再起動のたびに変わります）
- Lightsail の「アカウント」→「SSH キー」で、該当リージョンにキーが存在し、インスタンス作成時にそのキーを選択しているか確認

### rsync で "Permission denied" や "No such file or directory"

- テーマ用ディレクトリを手動で作成し、`bitnami` が書き込めるようにする（「4.4 テーマ用ディレクトリの権限」を参照）
- `DEPLOY_PATH` が実際の Bitnami のパス（`/opt/bitnami/wordpress/wp-content/themes/company-hp`）と一致しているか確認

---

## 10. 参考

- [Deploy and manage WordPress on Lightsail（AWS）](https://docs.aws.amazon.com/lightsail/latest/userguide/amazon-lightsail-quick-start-guide-wordpress.html)
- [GitHub Actions で VPS（Lightsail）にデプロイ](https://shugomatsuzawa.com/techblog/2024/03/13/358/)
- [AWS Lightsail + GitHub Actions で低コスト Web アプリインフラ構築](https://qiita.com/aosan/items/e0380933540b10d22fee)
