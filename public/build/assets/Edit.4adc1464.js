import{g as p,T as x,K as h,U as $,h as j,f as v,a as i,u,w as m,F as N,o as n,Z as E,b as t,d as c,t as _,l as d,n as k,e as F,c as f,m as Y,v as A,i as P}from"./app.024e39e5.js";import{_ as q}from"./Authenticated.9fe60fc0.js";import{_ as y}from"./Button.33536ddb.js";import{_ as H}from"./DatePicker.53db14f6.js";import{_ as V}from"./FormInput.a4bc002f.js";import{_ as B}from"./MultiSelect.35807366.js";import{_ as I}from"./SearchVendCodeInput.c14ccca6.js";import{r as K}from"./ArrowUturnLeftIcon.00eea8ad.js";import{r as T}from"./ArrowUturnDownIcon.2b6517a5.js";import{r as z,a as R}from"./PlayIcon.cf546182.js";import{r as Z}from"./CheckCircleIcon.71ce7f83.js";import"./open-closed.4c597dc4.js";import"./use-resolve-button-type.0e6f995d.js";import"./RectangleStackIcon.7a8167e6.js";import"./main.c0edd862.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.18709a47.js";import"./MagnifyingGlassCircleIcon.83439c67.js";import"./platform.ff812502.js";const G={class:"font-semibold text-xl text-gray-800 leading-tight"},J={key:0},L={key:1},Q=t("br",null,null,-1),W={key:2},X=t("br",null,null,-1),ee={class:"flex flex-col"},te={class:"font-bold"},se={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},oe={class:"mt-6 flex flex-col"},ae={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},re={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5"},le=["onSubmit"],ne={class:"grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2"},ie={class:"sm:col-span-6"},de={class:"sm:col-span-6"},ue={class:"relative flex items-start"},me={class:"flex h-6 items-center"},ce=["disabled"],ve=t("div",{class:"ml-3 text-sm leading-6"},[t("label",{for:"is_customer",class:"font-medium text-gray-900"},"Customer Binding?"),c(" "+_(" ")+" "),t("span",{id:"is_customer",class:"text-gray-500"},[t("span",{class:"sr-only"},"Customer Binding?"),c("retrieve customer data from cms")])],-1),pe={key:0,class:"sm:col-span-6"},_e=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Customer ",-1),fe={key:1,class:"sm:col-span-6"},ge={class:"sm:col-span-6"},ye=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Operator ",-1),be={class:"sm:col-span-3"},xe={class:"sm:col-span-6"},he={class:"sm:col-span-6"},ke={class:"flex space-x-1 mt-5 justify-end"},Ve=t("span",null," Back ",-1),we=t("span",null," Unbind Customer ",-1),Ce={key:0,class:"flex"},Se={key:1,class:"flex"},$e=t("span",null," Save ",-1),Re={__name:"Edit",props:{adminCustomerOptions:Object,operatorOptions:Object,vend:Object,type:String},setup(s){const l=s,w=p([]);p([]);const e=p(x(S()));p(!1);const C=p([]),b=p("");h().props.auth.operatorCountry,h().props.auth.operatorRole;const g=h().props.auth.permissions;p($().format("HH:mm:ss")),j(()=>{l.type=="create"?b.value="Create New":b.value="Edit",w.value=l.adminCustomerOptions.map(o=>({id:o.id,full_name:o.cust_id+" - "+o.company})),C.value=[...l.operatorOptions.data.map(o=>({id:o.id,full_name:o.full_name}))],e.value=l.vend&&l.vend.id?x(l.vend):x(S()),e.value.name=l.vend.name,l.vend.customer_id&&(e.value.is_customer=!0,e.value.customer_id={id:l.vend.person_id,full_name:l.vend.customer_code+" - "+l.vend.customer_name}),l.vend.operator_id&&(e.value.operator_id={id:l.vend.operator_id,full_name:l.vend.operator_name})});function S(){return{code:"",name:"",begin_date:$().format("YYYY-MM-DD"),customer_id:"",operator_id:"",serial_num:"",termination_date:"",private_key:""}}function O(o){e.value.code=o.code}function U(){e.value.clearErrors(),l.type==="create"&&e.value.transform(o=>({...o,customer_id:o.customer_id?o.customer_id.id:null,operator_id:o.operator_id?o.operator_id.id:null})).post("/vends/create",{preserveState:!0,replace:!0}),l.type==="update"&&e.value.transform(o=>({...o,customer_id:o.customer_id?o.customer_id.id:null,operator_id:o.operator_id?o.operator_id.id:null})).post("/vends/"+e.value.id+"/update",{preserveState:!0,replace:!0})}function D(o){e.value.post("/vends/"+o+"/unbind",{onSuccess:()=>{e.value.is_customer=!1,e.value.customer_id="",e.value.begin_date=""},preserveState:!0,replace:!0})}function M(){e.value.post("/settings/"+e.value.id+"/toggle-activation",{})}return(o,a)=>(n(),v(N,null,[i(u(E),{title:"VM Management"}),i(q,null,{header:m(()=>[t("h2",G,[c(_(b.value)+" Vending Machine ",1),s.type=="update"?(n(),v("span",J,_(s.vend.code),1)):d("",!0),s.vend.customer_name?(n(),v("span",L,[Q,c(" "+_(s.vend.customer_code)+" - "+_(s.vend.customer_name),1)])):!s.vend.customer_name&&s.vend.name?(n(),v("span",W,[X,c(" "+_(s.vend.name),1)])):d("",!0)]),t("span",null,[s.vend.is_active!=null?(n(),v("div",{key:0,class:k(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-fit",[s.vend.is_active?"bg-green-200":"bg-red-200"]])},[t("div",ee,[t("span",te,_(s.vend.is_active?"Active":"Inactive"),1)])],2)):d("",!0)])]),default:m(()=>[t("div",se,[t("div",oe,[t("div",ae,[t("div",re,[t("form",{onSubmit:F(U,["prevent"]),id:"submit"},[t("div",ne,[t("div",ie,[s.type=="update"?(n(),f(V,{key:0,modelValue:e.value.code,"onUpdate:modelValue":a[0]||(a[0]=r=>e.value.code=r),error:e.value.errors.code,required:"true",disabled:s.vend.code},{default:m(()=>[c(" Code ")]),_:1},8,["modelValue","error","disabled"])):d("",!0),s.type=="create"?(n(),f(I,{key:1,modelValue:e.value.code,"onUpdate:modelValue":a[1]||(a[1]=r=>e.value.code=r),onSelected:O,required:"true",error:e.value.errors.code},{default:m(()=>[c(" Code ")]),_:1},8,["modelValue","error"])):d("",!0)]),t("div",de,[t("div",ue,[t("div",me,[Y(t("input",{"aria-describedby":"is_customer","onUpdate:modelValue":a[2]||(a[2]=r=>e.value.is_customer=r),disabled:s.vend.customer_id&&s.type=="update",name:"is_customer",type:"checkbox",class:k(["h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600",[s.vend.customer_id&&s.type=="update"?"bg-gray-200 hover:cursor-not-allowed":""]])},null,10,ce),[[A,e.value.is_customer]])]),ve])]),e.value.is_customer?(n(),v("div",pe,[_e,i(B,{modelValue:e.value.customer_id,"onUpdate:modelValue":a[3]||(a[3]=r=>e.value.customer_id=r),options:w.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):d("",!0),e.value.is_customer?d("",!0):(n(),v("div",fe,[i(V,{modelValue:e.value.name,"onUpdate:modelValue":a[4]||(a[4]=r=>e.value.name=r),error:e.value.errors.name},{default:m(()=>[c(" Name ")]),_:1},8,["modelValue","error"])])),t("div",ge,[ye,i(B,{modelValue:e.value.operator_id,"onUpdate:modelValue":a[5]||(a[5]=r=>e.value.operator_id=r),options:C.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",be,[u(g).includes("update vends")?(n(),f(H,{key:0,modelValue:e.value.begin_date,"onUpdate:modelValue":a[6]||(a[6]=r=>e.value.begin_date=r),error:e.value.errors.begin_date,onInput:a[7]||(a[7]=r=>o.onDateFromChanged())},{default:m(()=>[c(" Begin Date ")]),_:1},8,["modelValue","error"])):d("",!0)]),t("div",xe,[i(V,{modelValue:e.value.private_key,"onUpdate:modelValue":a[8]||(a[8]=r=>e.value.private_key=r),error:e.value.errors.private_key,disabled:!u(g).includes("update vends")},{default:m(()=>[c(" Private Key ")]),_:1},8,["modelValue","error","disabled"])])]),t("div",he,[t("div",ke,[i(u(P),{href:"/settings"},{default:m(()=>[i(y,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"},{default:m(()=>[i(u(K),{class:"w-4 h-4"}),Ve]),_:1})]),_:1}),s.vend.customer_id&&u(g).includes("update vends")?(n(),f(y,{key:0,type:"button",class:"bg-yellow-500 hover:bg-yellow-600 text-white flex space-x-1",onClick:a[9]||(a[9]=r=>D(e.value.id))},{default:m(()=>[i(u(T),{class:"w-4 h-4"}),we]),_:1})):d("",!0),u(g).includes("update vends")&&s.vend.is_active!=null?(n(),f(y,{key:1,type:"button",class:k(["text-white flex space-x-1",[s.vend.is_active?"bg-yellow-500 hover:bg-yellow-600":"bg-green-500 hover:bg-green-600"]]),onClick:a[10]||(a[10]=r=>M())},{default:m(()=>[s.vend.is_active?d("",!0):(n(),v("span",Ce,[i(u(z),{class:"w-4 h-4 pt-1"}),c("Activate ")])),s.vend.is_active?(n(),v("span",Se,[i(u(R),{class:"w-4 h-4 pt-1"}),c("Deactivate ")])):d("",!0)]),_:1},8,["class"])):d("",!0),u(g).includes("update vends")?(n(),f(y,{key:2,type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:m(()=>[i(u(Z),{class:"w-4 h-4"}),$e]),_:1})):d("",!0)])])],40,le)])])])])]),_:1})],64))}};export{Re as default};
