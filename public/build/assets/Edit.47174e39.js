import{_ as f}from"./Authenticated.71dc77f1.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.741a9f44.js";import{g as a,K as m,h as _,f as o,a as c,u as v,w as u,F as x,o as n,Z as g,b as t,d as i,t as s,l as p}from"./app.c1ecacbd.js";import"./open-closed.3e73817b.js";import"./use-resolve-button-type.30304aad.js";import"./RectangleStackIcon.221714de.js";const y={class:"font-semibold text-xl text-gray-800 leading-tight"},b={key:0},k={key:1},N=t("br",null,null,-1),V={key:2},w=t("br",null,null,-1),O={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},C={class:"mt-6 flex flex-col"},M={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},B={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},K={__name:"Edit",props:{operatorOptions:Object,vend:Object,type:String},setup(e){const d=e;a([]),a(!1),a([]);const h=a([]),l=a("");return m().props.auth.operatorCountry,m().props.auth.operatorRole,m().props.auth.permissions,a(moment().format("HH:mm:ss")),_(()=>{d.type=="create"?l.value="Create New":l.value="Edit",h.value=[{id:"all",full_name:"All"},...d.operatorOptions.data.map(r=>({id:r.id,full_name:r.full_name}))]}),(r,E)=>(n(),o(x,null,[c(v(g),{title:"VM Management"}),c(f,null,{header:u(()=>[t("h2",y,[i(s(l.value)+" Vending Machine ",1),e.type=="update"?(n(),o("span",b,s(e.vend.data.code),1)):p("",!0),e.vend.customer_name?(n(),o("span",k,[N,i(" "+s(e.vend.customer_code)+" - "+s(e.vend.customer_name),1)])):!e.vend.customer_name&&e.vend.name?(n(),o("span",V,[w,i(" "+s(e.vend.name),1)])):p("",!0)])]),default:u(()=>[t("div",O,[t("div",C,[t("div",M,[t("div",B,s(e.vend.data),1)])])])]),_:1})],64))}};export{K as default};
