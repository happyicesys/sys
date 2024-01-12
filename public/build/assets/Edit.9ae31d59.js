import{o as r,f as m,b as t,g as y,c as f,w as u,a as i,u as n,r as A,l as c,F as N,k as I,t as g,T as $,Q as S,U as M,h as q,Z as E,d as p,n as B,e as F,m as Y,v as Z,i as P}from"./app.242e5fba.js";import{_ as z}from"./Authenticated.3a544bed.js";import{_ as C}from"./Button.be945d63.js";import{_ as H}from"./DatePicker.9e0ab4aa.js";import{_ as O}from"./FormInput.15b0f65d.js";import{_ as D}from"./MultiSelect.fb25c41f.js";import{e as L,o as T,t as K,r as Q,l as R,a as G,Z as J}from"./MagnifyingGlassCircleIcon.10f1e7b4.js";import{r as W}from"./ArrowUturnLeftIcon.88830442.js";import{r as X}from"./ArrowUturnDownIcon.9dae651e.js";import{r as ee}from"./PauseCircleIcon.cd479fa6.js";import{r as te}from"./CheckCircleIcon.a28321ba.js";import"./keyboard.034c1cc1.js";import"./use-resolve-button-type.ce060f77.js";import"./RectangleStackIcon.e3df9db3.js";import"./main.46495f95.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.1155e6b8.js";import"./disposables.a3cad5e2.js";function se(s,l){return r(),m("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true","data-slot":"icon"},[t("path",{d:"M6.3 2.84A1.5 1.5 0 0 0 4 4.11v11.78a1.5 1.5 0 0 0 2.3 1.27l9.344-5.891a1.5 1.5 0 0 0 0-2.538L6.3 2.841Z"})])}const oe={class:"flex space-x-1"},ae={key:0,class:"text-red-500"},re={class:"relative mt-1"},le=["onClick"],ne={class:"block truncate"},de={key:1,class:"text-sm text-red-600"},ie={__name:"SearchVendCodeInput",props:{modelValue:[String,Number],required:[Boolean,String],error:String},emits:["update:modelValue","selected"],setup(s,{emit:l}){const b=l,e=y([]),w=_.debounce(async v=>{if(!v.target.value.length){e.value=[];return}axios({method:"get",url:"/api/vends/search/"+v.target.value}).then(x=>{e.value=x.data}).catch(x=>{console.log(x)})},300);function k(v){b("update:modelValue",v.target.value),w(v)}function h(v){b("selected",v)}return(v,x)=>(r(),f(n(J),{as:"div"},{default:u(()=>[t("div",oe,[i(n(L),{class:"block text-sm font-medium text-gray-700"},{default:u(()=>[A(v.$slots,"default")]),_:3}),s.required?(r(),m("span",ae," * ")):c("",!0)]),t("div",re,[i(n(T),{class:"w-full rounded-md border border-gray-300 bg-white py-2 pl-3 pr-10 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm",onInput:k,value:s.modelValue},null,8,["value"]),i(n(K),{class:"absolute inset-y-0 right-0 flex items-center rounded-r-md px-2 focus:outline-none"},{default:u(()=>[i(n(Q),{class:"h-5 w-5 text-gray-400","aria-hidden":"true"})]),_:1}),e.value.length>0?(r(),f(n(R),{key:0,class:"absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"},{default:u(()=>[(r(!0),m(N,null,I(e.value,V=>(r(),f(n(G),{as:"template"},{default:u(()=>[t("li",{class:"relative cursor-default select-none py-2 pl-3 pr-9 hover:bg-gray-100",onClick:U=>h(V)},[t("span",ne,g(V.vend_code),1)],8,le)]),_:2},1024))),256))]),_:1})):c("",!0),s.error?(r(),m("div",de,g(s.error),1)):c("",!0)])]),_:3}))}},ue={class:"font-semibold text-xl text-gray-800 leading-tight"},ce={key:0},me={key:1},ve=t("br",null,null,-1),pe={key:2},fe=t("br",null,null,-1),_e={class:"flex flex-col"},ge={class:"font-bold"},ye={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},he={class:"mt-6 flex flex-col"},xe={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},be={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5"},ke={class:"grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2"},we={class:"sm:col-span-6"},Ve={class:"sm:col-span-6"},Ce={class:"relative flex items-start"},$e={class:"flex h-6 items-center"},Se=["disabled"],Be=t("div",{class:"ml-3 text-sm leading-6"},[t("label",{for:"is_customer",class:"font-medium text-gray-900"},"Customer Binding?"),p(" "+g(" ")+" "),t("span",{id:"is_customer",class:"text-gray-500"},[t("span",{class:"sr-only"},"Customer Binding?"),p("retrieve customer data from cms")])],-1),Oe={key:0,class:"sm:col-span-6"},Ue=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Customer ",-1),Me={key:1,class:"sm:col-span-6"},De={class:"sm:col-span-6"},Ne=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Operator ",-1),je={class:"sm:col-span-3"},Ae={class:"sm:col-span-6"},Ie={class:"sm:col-span-6"},qe={class:"flex flex-col space-y-1 sm:flex-row sm:space-x-1 sm:space-y-0 mt-5 justify-end"},Ee=t("span",null," Back ",-1),Fe=t("span",null," Unbind Customer ",-1),Ye={key:0,class:"flex"},Ze={key:1,class:"flex"},Pe=t("span",null," Save ",-1),lt={__name:"Edit",props:{adminCustomerOptions:Object,operatorOptions:Object,vend:Object,type:String},setup(s){const l=s,b=y([]);y([]);const e=y($(v()));y(!1);const w=y([]),k=y("");S().props.auth.operatorCountry,S().props.auth.operatorRole;const h=S().props.auth.permissions;y(M().format("HH:mm:ss")),q(()=>{l.type=="create"?k.value="Create New":k.value="Edit",b.value=l.adminCustomerOptions.map(o=>({id:o.id,full_name:o.cust_id+" - "+o.company})),w.value=[...l.operatorOptions.data.map(o=>({id:o.id,full_name:o.full_name}))],e.value=l.vend&&l.vend.id?$(l.vend):$(v()),e.value.name=l.vend.name,l.vend.customer_id&&(e.value.is_customer=!0,e.value.customer_id={id:l.vend.person_id,full_name:l.vend.customer_code+" - "+l.vend.customer_name}),l.vend.operator_id&&(e.value.operator_id={id:l.vend.operator_id,full_name:l.vend.operator_name})});function v(){return{code:"",name:"",begin_date:M().format("YYYY-MM-DD"),customer_id:"",operator_id:"",serial_num:"",termination_date:"",private_key:""}}function x(o){e.value.code=o.code}function V(){e.value.clearErrors(),l.type==="create"&&e.value.transform(o=>({...o,customer_id:o.customer_id?o.customer_id.id:null,operator_id:o.operator_id?o.operator_id.id:null})).post("/vends/create",{preserveState:!0,replace:!0}),l.type==="update"&&e.value.transform(o=>({...o,customer_id:o.customer_id?o.customer_id.id:null,operator_id:o.operator_id?o.operator_id.id:null})).post("/vends/"+e.value.id+"/update",{preserveState:!0,replace:!0})}function U(o){e.value.post("/vends/"+o+"/unbind",{onSuccess:()=>{e.value.is_customer=!1,e.value.customer_id="",e.value.begin_date=""},preserveState:!0,replace:!0})}function j(){e.value.post("/settings/"+e.value.id+"/toggle-activation",{})}return(o,a)=>(r(),m(N,null,[i(n(E),{title:"VM Management"}),i(z,null,{header:u(()=>[t("h2",ue,[p(g(k.value)+" Vending Machine ",1),s.type=="update"?(r(),m("span",ce,g(s.vend.code),1)):c("",!0),s.vend.customer_name?(r(),m("span",me,[ve,p(" "+g(s.vend.customer_code)+" - "+g(s.vend.customer_name),1)])):!s.vend.customer_name&&s.vend.name?(r(),m("span",pe,[fe,p(" "+g(s.vend.name),1)])):c("",!0)]),t("span",null,[s.vend.is_active!=null?(r(),m("div",{key:0,class:B(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-fit",[s.vend.is_active?"bg-green-200":"bg-red-200"]])},[t("div",_e,[t("span",ge,g(s.vend.is_active?"Active":"Inactive"),1)])],2)):c("",!0)])]),default:u(()=>[t("div",ye,[t("div",he,[t("div",xe,[t("div",be,[t("form",{onSubmit:F(V,["prevent"]),id:"submit"},[t("div",ke,[t("div",we,[s.type=="update"?(r(),f(O,{key:0,modelValue:e.value.code,"onUpdate:modelValue":a[0]||(a[0]=d=>e.value.code=d),error:e.value.errors.code,required:"true",disabled:s.vend.code},{default:u(()=>[p(" Code ")]),_:1},8,["modelValue","error","disabled"])):c("",!0),s.type=="create"?(r(),f(ie,{key:1,modelValue:e.value.code,"onUpdate:modelValue":a[1]||(a[1]=d=>e.value.code=d),onSelected:x,required:"true",error:e.value.errors.code},{default:u(()=>[p(" Code ")]),_:1},8,["modelValue","error"])):c("",!0)]),t("div",Ve,[t("div",Ce,[t("div",$e,[Y(t("input",{"aria-describedby":"is_customer","onUpdate:modelValue":a[2]||(a[2]=d=>e.value.is_customer=d),disabled:s.vend.customer_id&&s.type=="update",name:"is_customer",type:"checkbox",class:B(["h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600",[s.vend.customer_id&&s.type=="update"?"bg-gray-200 hover:cursor-not-allowed":""]])},null,10,Se),[[Z,e.value.is_customer]])]),Be])]),e.value.is_customer?(r(),m("div",Oe,[Ue,i(D,{modelValue:e.value.customer_id,"onUpdate:modelValue":a[3]||(a[3]=d=>e.value.customer_id=d),options:b.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):c("",!0),e.value.is_customer?c("",!0):(r(),m("div",Me,[i(O,{modelValue:e.value.name,"onUpdate:modelValue":a[4]||(a[4]=d=>e.value.name=d),error:e.value.errors.name},{default:u(()=>[p(" Name ")]),_:1},8,["modelValue","error"])])),t("div",De,[Ne,i(D,{modelValue:e.value.operator_id,"onUpdate:modelValue":a[5]||(a[5]=d=>e.value.operator_id=d),options:w.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",je,[n(h).includes("update vends")?(r(),f(H,{key:0,modelValue:e.value.begin_date,"onUpdate:modelValue":a[6]||(a[6]=d=>e.value.begin_date=d),error:e.value.errors.begin_date,onInput:a[7]||(a[7]=d=>o.onDateFromChanged())},{default:u(()=>[p(" Begin Date ")]),_:1},8,["modelValue","error"])):c("",!0)]),t("div",Ae,[i(O,{modelValue:e.value.private_key,"onUpdate:modelValue":a[8]||(a[8]=d=>e.value.private_key=d),error:e.value.errors.private_key,disabled:!n(h).includes("update vends")},{default:u(()=>[p(" Private Key ")]),_:1},8,["modelValue","error","disabled"])])]),t("div",Ie,[t("div",qe,[i(n(P),{href:"/settings",class:"bg-gray-300 hover:bg-gray-400 text-gray-700 rounded"},{default:u(()=>[i(C,{class:"space-x-1"},{default:u(()=>[i(n(W),{class:"w-4 h-4"}),Ee]),_:1})]),_:1}),s.vend.customer_id&&n(h).includes("update vends")?(r(),f(C,{key:0,type:"button",class:"bg-yellow-500 hover:bg-yellow-600 text-white flex space-x-1",onClick:a[9]||(a[9]=d=>U(e.value.id))},{default:u(()=>[i(n(X),{class:"w-4 h-4"}),Fe]),_:1})):c("",!0),n(h).includes("update vends")&&s.vend.is_active!=null?(r(),f(C,{key:1,type:"button",class:B(["text-white flex space-x-1",[s.vend.is_active?"bg-yellow-500 hover:bg-yellow-600":"bg-green-500 hover:bg-green-600"]]),onClick:a[10]||(a[10]=d=>j())},{default:u(()=>[s.vend.is_active?c("",!0):(r(),m("span",Ye,[i(n(se),{class:"w-4 h-4 pt-1"}),p("Activate ")])),s.vend.is_active?(r(),m("span",Ze,[i(n(ee),{class:"w-4 h-4 pt-1"}),p("Deactivate ")])):c("",!0)]),_:1},8,["class"])):c("",!0),n(h).includes("update vends")?(r(),f(C,{key:2,type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:u(()=>[i(n(te),{class:"w-4 h-4"}),Pe]),_:1})):c("",!0)])])],32)])])])])]),_:1})],64))}};export{lt as default};
