use crate::database::schema;

use chrono::NaiveDateTime;
use diesel::prelude::*;
use serde::Serialize;

#[derive(Queryable, Selectable, Serialize)]
#[diesel(table_name = schema::images)]
#[diesel(check_for_backend(diesel::sqlite::Sqlite))]
pub struct Image {
    pub id: String,
    pub filename: String,
    pub extension: String,
    pub mime: String,
    pub secret_key: String,
    pub uploaded_at: NaiveDateTime,
    pub modified_at: NaiveDateTime,
    pub expires_at: Option<NaiveDateTime>,
    pub size: i32,
    pub visibility: i32,
    pub tags: Option<String>,
    pub password: Option<String>,
}

#[derive(Insertable)]
#[diesel(table_name = schema::images)]
pub struct NewImage<'a> {
    pub id: &'a str,
    pub filename: &'a str,
    pub extension: &'a str,
    pub mime: &'a str,
    pub secret_key: &'a str,
    pub expires_at: Option<NaiveDateTime>,
    pub size: i32,
    pub visibility: i32,
    pub tags: Option<String>,
    pub password: Option<String>,
}
