# 会社HP テーマ（WordPress）

Lightsail 上の WordPress 用テーマです。React 版と同じデザイン（ヘッダー・フッター・ヒーロー・セクション）を PHP で再現しています。

## 必要な環境

- WordPress 5.0 以上
- 親テーマ不要（スタンドアロン）

## インストール

1. この `company-hp` フォルダを WordPress の `wp-content/themes/` に配置
2. 管理画面 → 外観 → テーマ で「会社HP（屋号）」を有効化
3. 表示設定で「ホームページの表示」を「固定ページ」にし、トップ用の固定ページを指定（任意）
4. 固定ページで「事業者について」「お問い合わせ」を追加し、スラッグを `about` / `contact` にすると、メニューと URL が揃います
5. 外観 → メニュー で「メインメニュー」にページを追加

## デプロイ（Lightsail）

リポジトリの `.github/workflows/deploy.yml` を WordPress テーマ用に設定し、`wordpress-theme/company-hp/` の中身を Lightsail の次のパスに rsync します。

- Bitnami: `/opt/bitnami/wordpress/wp-content/themes/company-hp/`
- 通常の WordPress: `~/wordpress/wp-content/themes/company-hp/`

詳細はリポジトリ直下の `docs/LIGHTSAIL_DEPLOY.md` を参照してください。
