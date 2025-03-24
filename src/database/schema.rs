// @generated automatically by Diesel CLI.

diesel::table! {
    images (id) {
        id -> Text,
        filename -> Text,
        extension -> Text,
        mime -> Text,
        secret_key -> Text,
        uploaded_at -> Timestamp,
        modified_at -> Timestamp,
        expires_at -> Nullable<Timestamp>,
        size -> Integer,
        visibility -> Integer,
        tags -> Nullable<Text>,
        password -> Nullable<Text>,
    }
}
