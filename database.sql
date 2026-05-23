--
-- PostgreSQL database dump
--

\restrict D3zUyLSTGwDL8zPrVyn7PiHzL4JfHehTfcidqiVEQoR4siCfRy3jvK2DamYgaTt

-- Dumped from database version 16.10
-- Dumped by pg_dump version 16.10

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: achievements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.achievements (
    id integer NOT NULL,
    donor_id integer NOT NULL,
    type character varying(50) NOT NULL,
    title character varying(100) NOT NULL,
    description text,
    earned_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- Name: achievements_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.achievements_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: achievements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.achievements_id_seq OWNED BY public.achievements.id;


--
-- Name: audit_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.audit_logs (
    id integer NOT NULL,
    user_id integer,
    action character varying(100) NOT NULL,
    entity_type character varying(100),
    entity_id integer,
    old_values text,
    new_values text,
    ip_address character varying(45),
    user_agent character varying(500),
    created_at timestamp without time zone DEFAULT now()
);


--
-- Name: audit_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.audit_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: audit_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.audit_logs_id_seq OWNED BY public.audit_logs.id;


--
-- Name: blood_banks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.blood_banks (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    address text,
    city character varying(100),
    state character varying(100),
    phone character varying(30),
    email character varying(255),
    license_number character varying(100),
    working_hours character varying(100),
    has_24h_service boolean DEFAULT false,
    latitude numeric(10,7),
    longitude numeric(10,7),
    created_at timestamp without time zone DEFAULT now()
);


--
-- Name: blood_banks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.blood_banks_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: blood_banks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.blood_banks_id_seq OWNED BY public.blood_banks.id;


--
-- Name: blood_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.blood_requests (
    id integer NOT NULL,
    hospital_id integer,
    patient_blood_type character varying(5),
    units_needed integer DEFAULT 1,
    urgency character varying(20) DEFAULT 'normal'::character varying,
    status character varying(20) DEFAULT 'open'::character varying,
    required_date date,
    city character varying(100),
    state character varying(100),
    hospital_address text,
    notes text,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


--
-- Name: blood_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.blood_requests_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: blood_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.blood_requests_id_seq OWNED BY public.blood_requests.id;


--
-- Name: donation_history; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.donation_history (
    id integer NOT NULL,
    donor_id integer,
    request_id integer,
    hospital_id integer,
    donation_date date NOT NULL,
    blood_type character varying(5),
    units integer DEFAULT 1,
    created_at timestamp without time zone DEFAULT now()
);


--
-- Name: donation_history_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.donation_history_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: donation_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.donation_history_id_seq OWNED BY public.donation_history.id;


--
-- Name: donor_matches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.donor_matches (
    id integer NOT NULL,
    request_id integer,
    donor_id integer,
    status character varying(20) DEFAULT 'pending'::character varying,
    created_at timestamp without time zone DEFAULT now()
);


--
-- Name: donor_matches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.donor_matches_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: donor_matches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.donor_matches_id_seq OWNED BY public.donor_matches.id;


--
-- Name: donor_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.donor_profiles (
    id integer NOT NULL,
    user_id integer,
    full_name character varying(255) NOT NULL,
    phone character varying(30),
    blood_type character varying(5),
    address text,
    city character varying(100),
    state character varying(100),
    country character varying(100) DEFAULT 'India'::character varying,
    date_of_birth date,
    gender character varying(10),
    is_available boolean DEFAULT true,
    last_donation_date date,
    latitude numeric(10,7),
    longitude numeric(10,7),
    total_donations integer DEFAULT 0,
    tier character varying(20) DEFAULT 'bronze'::character varying,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


--
-- Name: donor_profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.donor_profiles_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: donor_profiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.donor_profiles_id_seq OWNED BY public.donor_profiles.id;


--
-- Name: hospital_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.hospital_profiles (
    id integer NOT NULL,
    user_id integer,
    hospital_name character varying(255) NOT NULL,
    phone character varying(30),
    address text,
    city character varying(100),
    state character varying(100),
    country character varying(100) DEFAULT 'India'::character varying,
    license_number character varying(100),
    latitude numeric(10,7),
    longitude numeric(10,7),
    is_verified boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


--
-- Name: hospital_profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.hospital_profiles_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: hospital_profiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.hospital_profiles_id_seq OWNED BY public.hospital_profiles.id;


--
-- Name: password_resets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_resets (
    id integer NOT NULL,
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    expires_at timestamp without time zone NOT NULL,
    used_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT now()
);


--
-- Name: password_resets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.password_resets_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: password_resets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.password_resets_id_seq OWNED BY public.password_resets.id;


--
-- Name: testimonials; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.testimonials (
    id integer NOT NULL,
    donor_id integer,
    recipient_name character varying(255),
    story text NOT NULL,
    rating integer DEFAULT 5,
    is_approved boolean DEFAULT false,
    created_at timestamp without time zone DEFAULT now()
);


--
-- Name: testimonials_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.testimonials_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: testimonials_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.testimonials_id_seq OWNED BY public.testimonials.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id integer NOT NULL,
    email character varying(255) NOT NULL,
    password character varying(255) NOT NULL,
    role character varying(20) DEFAULT 'donor'::character varying,
    is_active boolean DEFAULT true,
    email_verified_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: achievements id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.achievements ALTER COLUMN id SET DEFAULT nextval('public.achievements_id_seq'::regclass);


--
-- Name: audit_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_logs ALTER COLUMN id SET DEFAULT nextval('public.audit_logs_id_seq'::regclass);


--
-- Name: blood_banks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blood_banks ALTER COLUMN id SET DEFAULT nextval('public.blood_banks_id_seq'::regclass);


--
-- Name: blood_requests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blood_requests ALTER COLUMN id SET DEFAULT nextval('public.blood_requests_id_seq'::regclass);


--
-- Name: donation_history id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.donation_history ALTER COLUMN id SET DEFAULT nextval('public.donation_history_id_seq'::regclass);


--
-- Name: donor_matches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.donor_matches ALTER COLUMN id SET DEFAULT nextval('public.donor_matches_id_seq'::regclass);


--
-- Name: donor_profiles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.donor_profiles ALTER COLUMN id SET DEFAULT nextval('public.donor_profiles_id_seq'::regclass);


--
-- Name: hospital_profiles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hospital_profiles ALTER COLUMN id SET DEFAULT nextval('public.hospital_profiles_id_seq'::regclass);


--
-- Name: password_resets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_resets ALTER COLUMN id SET DEFAULT nextval('public.password_resets_id_seq'::regclass);


--
-- Name: testimonials id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.testimonials ALTER COLUMN id SET DEFAULT nextval('public.testimonials_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: achievements achievements_donor_id_type_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.achievements
    ADD CONSTRAINT achievements_donor_id_type_key UNIQUE (donor_id, type);


--
-- Name: achievements achievements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.achievements
    ADD CONSTRAINT achievements_pkey PRIMARY KEY (id);


--
-- Name: audit_logs audit_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_pkey PRIMARY KEY (id);


--
-- Name: blood_banks blood_banks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blood_banks
    ADD CONSTRAINT blood_banks_pkey PRIMARY KEY (id);


--
-- Name: blood_requests blood_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blood_requests
    ADD CONSTRAINT blood_requests_pkey PRIMARY KEY (id);


--
-- Name: donation_history donation_history_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.donation_history
    ADD CONSTRAINT donation_history_pkey PRIMARY KEY (id);


--
-- Name: donor_matches donor_matches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.donor_matches
    ADD CONSTRAINT donor_matches_pkey PRIMARY KEY (id);


--
-- Name: donor_matches donor_matches_request_id_donor_id_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.donor_matches
    ADD CONSTRAINT donor_matches_request_id_donor_id_key UNIQUE (request_id, donor_id);


--
-- Name: donor_profiles donor_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.donor_profiles
    ADD CONSTRAINT donor_profiles_pkey PRIMARY KEY (id);


--
-- Name: hospital_profiles hospital_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hospital_profiles
    ADD CONSTRAINT hospital_profiles_pkey PRIMARY KEY (id);


--
-- Name: password_resets password_resets_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_resets
    ADD CONSTRAINT password_resets_email_key UNIQUE (email);


--
-- Name: password_resets password_resets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_resets
    ADD CONSTRAINT password_resets_pkey PRIMARY KEY (id);


--
-- Name: testimonials testimonials_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.testimonials
    ADD CONSTRAINT testimonials_pkey PRIMARY KEY (id);


--
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: achievements achievements_donor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.achievements
    ADD CONSTRAINT achievements_donor_id_fkey FOREIGN KEY (donor_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: audit_logs audit_logs_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.audit_logs
    ADD CONSTRAINT audit_logs_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: blood_requests blood_requests_hospital_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.blood_requests
    ADD CONSTRAINT blood_requests_hospital_id_fkey FOREIGN KEY (hospital_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: donation_history donation_history_donor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.donation_history
    ADD CONSTRAINT donation_history_donor_id_fkey FOREIGN KEY (donor_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: donation_history donation_history_hospital_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.donation_history
    ADD CONSTRAINT donation_history_hospital_id_fkey FOREIGN KEY (hospital_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: donation_history donation_history_request_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.donation_history
    ADD CONSTRAINT donation_history_request_id_fkey FOREIGN KEY (request_id) REFERENCES public.blood_requests(id) ON DELETE SET NULL;


--
-- Name: donor_matches donor_matches_donor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.donor_matches
    ADD CONSTRAINT donor_matches_donor_id_fkey FOREIGN KEY (donor_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: donor_matches donor_matches_request_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.donor_matches
    ADD CONSTRAINT donor_matches_request_id_fkey FOREIGN KEY (request_id) REFERENCES public.blood_requests(id) ON DELETE CASCADE;


--
-- Name: donor_profiles donor_profiles_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.donor_profiles
    ADD CONSTRAINT donor_profiles_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: hospital_profiles hospital_profiles_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.hospital_profiles
    ADD CONSTRAINT hospital_profiles_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: testimonials testimonials_donor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.testimonials
    ADD CONSTRAINT testimonials_donor_id_fkey FOREIGN KEY (donor_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--

\unrestrict D3zUyLSTGwDL8zPrVyn7PiHzL4JfHehTfcidqiVEQoR4siCfRy3jvK2DamYgaTt

--
-- PostgreSQL database dump
--

\restrict jjJ8A4CbuSOgEJVeizFV73ZBhzk6xZShfJSu8Q3ghhK1Wnfc4zmyiWr0DN1YUnQ

-- Dumped from database version 16.10
-- Dumped by pg_dump version 16.10

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: blood_banks; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.blood_banks (id, name, address, city, state, phone, email, license_number, working_hours, has_24h_service, latitude, longitude, created_at) FROM stdin;
1	AIIMS Blood Bank	Ansari Nagar East, New Delhi	New Delhi	Delhi	011-26588500	\N	\N	24 Hours	t	\N	\N	2026-05-20 11:40:25.739593
2	Rotary Blood Bank	Connaught Place	New Delhi	Delhi	011-23366243	\N	\N	8am-8pm	f	\N	\N	2026-05-20 11:40:25.739593
3	KEM Hospital Blood Bank	Acharya Donde Marg, Parel	Mumbai	Maharashtra	022-24107000	\N	\N	24 Hours	t	\N	\N	2026-05-20 11:40:25.739593
4	Lilavati Hospital Blood Bank	Bandra Reclamation	Mumbai	Maharashtra	022-26751000	\N	\N	24 Hours	t	\N	\N	2026-05-20 11:40:25.739593
5	Apollo Hospital Blood Bank	Greams Road	Chennai	Tamil Nadu	044-28293333	\N	\N	24 Hours	t	\N	\N	2026-05-20 11:40:25.739593
6	Nimhans Blood Bank	Hosur Road, Bangalore	Bangalore	Karnataka	080-46110007	\N	\N	8am-6pm	f	\N	\N	2026-05-20 11:40:25.739593
7	PGI Blood Bank	Sector 12, Chandigarh	Chandigarh	Punjab	0172-2756565	\N	\N	24 Hours	t	\N	\N	2026-05-20 11:40:25.739593
8	SGPGI Blood Bank	Raebareli Road	Lucknow	Uttar Pradesh	0522-2494404	\N	\N	24 Hours	t	\N	\N	2026-05-20 11:40:25.739593
\.


--
-- Data for Name: testimonials; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.testimonials (id, donor_id, recipient_name, story, rating, is_approved, created_at) FROM stdin;
1	\N	Ravi Kumar's Family	My father needed O- blood urgently after his accident. Within 2 hours, LifeLine matched us with a donor in our city. He survived because of this platform. Forever grateful.	5	t	2026-05-20 11:40:29.968984
2	\N	Dr. Priya Sharma	As a hospital administrator, LifeLine has transformed how we handle emergency blood needs. The matching system is incredibly fast and reliable.	5	t	2026-05-20 11:40:29.968984
3	\N	Meera Singh	I donated blood for the first time through LifeLine. The process was so simple and knowing I helped save a life is the best feeling in the world.	5	t	2026-05-20 11:40:29.968984
\.


--
-- Name: blood_banks_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.blood_banks_id_seq', 8, true);


--
-- Name: testimonials_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.testimonials_id_seq', 3, true);


--
-- PostgreSQL database dump complete
--

\unrestrict jjJ8A4CbuSOgEJVeizFV73ZBhzk6xZShfJSu8Q3ghhK1Wnfc4zmyiWr0DN1YUnQ

