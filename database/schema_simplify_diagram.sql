/*
  DobroHub — упрощённая схема для ER-диаграммы (меньше таблиц и «ромбов»).

  Уже применено через: php database/run_schema_simplify.php
  Откат структуры: database/schema_simplify_diagram_rollback.sql (+ бэкап .bak БД)

  ОСТАЛОСЬ 6 сущностей:
    Users, Categories, Events, Registrations, News, EventOutcomes

  FK (4 связи — без лишних треугольников News/ActivityLog/Materials):
    Registrations → Users, Events
    Events → Categories
    EventOutcomes → Events

  Объединено в родительские таблицы:
    NewsImages        → News.CoverImagePath + News.GalleryJson
    EventOutcomeFiles → EventOutcomes.FilesJson
    ActivityLog       → вычисляется из Events + Registrations (CancelledAt)
    EventMaterials    → удалена (не использовалась)

  Сняты FK (столбцы остались для приложения):
    Events.CreatorUserID, News.AuthorUserID, News.RelatedEventID
*/
USE [VolunteerCommunity];
GO
