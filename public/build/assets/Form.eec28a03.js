import{i as y,u as V,j as D,o,c as E,a as n,w as c,T as R,d as t,g as l,p as d,t as m,n as f,b as _,f as u,F as z,m as A,e as q}from"./app.a148a0ce.js";import{_ as v}from"./Button.d03094fa.js";import{_ as h}from"./FormInput.171dd35c.js";import{_ as J}from"./FormTextarea.4f67bcc7.js";import{_ as K}from"./Modal.08e1db9d.js";import{_ as b}from"./MultiSelect.abaf8b0b.js";import{r as C}from"./PlusCircleIcon.77b85d5b.js";import{r as L}from"./ArrowUturnLeftIcon.1f62befc.js";import{r as Q}from"./CheckCircleIcon.e4e45e06.js";import{r as G}from"./BackspaceIcon.5a40654b.js";import"./open-closed.ce385b24.js";const H={class:"flex flex-col md:flex-row space-x-2"},W={key:0,class:"text-gray-600"},X={key:1},Y={key:2,class:"text-gray-600"},Z=["onSubmit"],I={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},ee={class:"sm:col-span-2"},te=u(" Code "),ae={class:"sm:col-span-4"},se=u(" Name "),oe={class:"sm:col-span-6"},le=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Country ",-1),ne={key:0,class:"text-sm text-red-600"},re={class:"sm:col-span-6"},de=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Timezone ",-1),ie={key:0,class:"text-sm text-red-600"},me={class:"sm:col-span-6"},ce=u(" Remarks "),ue={key:0,class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},pe=t("div",{class:"relative"},[t("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[t("div",{class:"w-full border-t border-gray-300"})]),t("div",{class:"relative flex justify-center"},[t("span",{class:"px-3 bg-white text-lg font-medium text-gray-900"}," QR Payment Gateway(s) ")])],-1),ye=[pe],_e=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Payment Gateway ",-1),ve={key:0,class:"text-sm text-red-600"},ge={key:2,class:"sm:col-span-1"},xe=t("span",null," Add ",-1),fe={key:3,class:"sm:col-span-3"},we={key:4,class:"sm:col-span-3"},he={key:5,class:"sm:col-span-3"},be={key:6,class:"sm:col-span-3"},ke=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"},[u(" Type "),t("span",{class:"text-red-500"},"*")],-1),Ve={key:0,class:"text-sm text-red-600"},Ce={key:7,class:"sm:col-span-6 flex justify-end"},Oe=t("span",null," Add ",-1),Be={key:8,class:"sm:col-span-6 flex flex-col mt-3"},$e={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},je={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},Pe={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},Se={class:"min-w-full divide-y divide-gray-300"},ze=t("thead",{class:"bg-gray-50"},[t("tr",null,[t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," # "),t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Name "),t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Type "),t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Private Key "),t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Action ")])],-1),Ae={class:"bg-white"},Ge={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Ne={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},Ue={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Te={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Me={class:"whitespace-nowrap py-4 text-sm text-center"},Fe={key:0},De=t("td",{colspan:"5",class:"whitespace-nowrap py-4 text-sm font-medium text-center"}," No Result Found ",-1),Ee=[De],Re={key:9,class:"sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3"},qe=t("div",{class:"relative"},[t("div",{class:"absolute inset-0 flex items-center","aria-hidden":"true"},[t("div",{class:"w-full border-t border-gray-300"})]),t("div",{class:"relative flex justify-center"},[t("span",{class:"px-3 bg-white text-lg font-medium text-gray-900"}," Access Vending Machine(s) ")])],-1),Je=[qe],Ke={key:10,class:"sm:col-span-5"},Le=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Vending Machine to Bind ",-1),Qe={key:0,class:"text-sm text-red-600"},He={key:11,class:"sm:col-span-1"},We=t("span",null," Add ",-1),Xe={key:12,class:"sm:col-span-6 flex flex-col mt-3"},Ye={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},Ze={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},Ie={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},et={class:"min-w-full divide-y divide-gray-300"},tt=t("thead",{class:"bg-gray-50"},[t("tr",null,[t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," # "),t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Vend ID "),t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Name "),t("th",{scope:"col",class:"px-3 py-3.5 text-center text-sm font-semibold text-gray-900"}," Action ")])],-1),at={class:"bg-white"},st={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},ot={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},lt={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},nt={key:0},rt=t("br",null,null,-1),dt={key:1},it={class:"whitespace-nowrap py-4 text-sm text-center"},mt={key:0},ct=t("td",{colspan:"4",class:"whitespace-nowrap py-4 text-sm font-medium text-red-600 text-center"}," No Binding = Access to All ",-1),ut=[ct],pt={class:"sm:col-span-6"},yt={class:"flex space-x-1 mt-5 justify-end"},_t=t("span",null," Back ",-1),vt=t("span",null," Save ",-1),$t={__name:"Form",props:{countries:[Array,Object],operator:Object,timezones:[Array,Object],type:String,showModal:Boolean,countryPaymentGateways:[Array,Object],operatorPaymentGatewayTypes:[Array,Object],unbindedVends:[Array,Object]},emits:["modalClose"],setup(g,{emit:O}){const i=g,B=y([]),e=y(V(P())),$=y([]),k=y([]),x=y([]),j=y([]),p=y([]);D(()=>{k.value=i.countryPaymentGateways.data,B.value=i.countries.data,$.value=i.timezones.map((r,s)=>({id:s,name:r})),k.value=i.countryPaymentGateways.data,j.value=i.operatorPaymentGatewayTypes,p.value=i.unbindedVends.data,x.value=i.operator.operatorPaymentGateways,e.value=i.operator?V(i.operator):V(P())});function P(){return{id:"",code:"",name:"",country_id:"",payment_gateway_id:"",payment_gateway_type:"",payment_gateway_key1:"",payment_gateway_key2:"",payment_gateway_key3:"",timezone:"",remarks:"",vend_id:""}}function N(){i.operator.vends.indexOf(e.value.vend_id)<0&&(i.operator.vends.push(e.value.vend_id),i.operator.vends.sort((r,s)=>r.code-s.code),p.value.splice(p.value.indexOf(e.value.vend_id),1),p.value.sort((r,s)=>r.code-s.code))}function U(r){i.operator.vends.splice(i.operator.vends.indexOf(r),1),p.value.push(r),p.value.sort((s,a)=>s.code-a.code)}function S(){x.value.indexOf(e.value.payment_gateway)<0&&x.value.push({id:e.value.payment_gateway_id,key1:e.value.payment_gateway_key1,key2:e.value.payment_gateway_key2,key3:e.value.payment_gateway_key3,type:e.value.payment_gateway_type.id,paymentGateway:JSON.parse(JSON.stringify(e.value.payment_gateway))})}function T(r){x.value.splice(x.value.indexOf(r),1)}function M(){e.value.clearErrors(),i.type==="create"&&e.value.transform(r=>({...r,timezone:r.timezone.name,country_id:r.country_id.id})).post("/operators/create",{onSuccess:()=>{O("modalClose")},preserveState:!0,replace:!0}),i.type==="update"&&e.value.transform(r=>({...r,timezone:r.timezone.name,country_id:r.country_id.id,operator:i.operator,paymentGateways:x.value})).post("/operators/"+e.value.id+"/update",{onSuccess:()=>{O("modalClose")},preserveState:!0,replace:!0})}return(r,s)=>(o(),E(R,{to:"body"},[n(K,{open:g.showModal,onModalClose:s[15]||(s[15]=a=>r.$emit("modalClose"))},{header:c(()=>[t("div",H,[i.operator?(o(),l("span",W," Editing ")):d("",!0),i.operator?(o(),l("span",X,m(i.operator.name),1)):(o(),l("span",Y," Create New Operator "))])]),default:c(()=>[t("form",{onSubmit:q(M,["prevent"]),id:"submit"},[t("div",I,[t("div",ee,[n(h,{modelValue:e.value.code,"onUpdate:modelValue":s[0]||(s[0]=a=>e.value.code=a),error:e.value.errors.code},{default:c(()=>[te]),_:1},8,["modelValue","error"])]),t("div",ae,[n(h,{modelValue:e.value.name,"onUpdate:modelValue":s[1]||(s[1]=a=>e.value.name=a),error:e.value.errors.name,required:"true"},{default:c(()=>[se]),_:1},8,["modelValue","error"])]),t("div",oe,[le,n(b,{modelValue:e.value.country_id,"onUpdate:modelValue":s[2]||(s[2]=a=>e.value.country_id=a),options:B.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),e.value.errors.country_id?(o(),l("div",ne,m(e.value.errors.country_id),1)):d("",!0)]),t("div",re,[de,n(b,{modelValue:e.value.timezone,"onUpdate:modelValue":s[3]||(s[3]=a=>e.value.timezone=a),options:$.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"]),e.value.errors.timezone?(o(),l("div",ie,m(e.value.errors.timezone),1)):d("",!0)]),t("div",me,[n(J,{modelValue:e.value.remarks,"onUpdate:modelValue":s[4]||(s[4]=a=>e.value.remarks=a),error:e.value.errors.remarks},{default:c(()=>[ce]),_:1},8,["modelValue","error"])]),e.value.id?(o(),l("div",ue,ye)):d("",!0),e.value.id?(o(),l("div",{key:1,class:f([e.value.payment_gateway?"sm:col-span-6":"sm:col-span-5"])},[_e,n(b,{modelValue:e.value.payment_gateway,"onUpdate:modelValue":s[5]||(s[5]=a=>e.value.payment_gateway=a),options:k.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1",ref:"multiselect"},null,8,["modelValue","options"]),e.value.errors.payment_gateway?(o(),l("div",ve,m(e.value.errors.payment_gateway),1)):d("",!0)],2)):d("",!0),e.value.id&&!e.value.payment_gateway?(o(),l("div",ge,[n(v,{type:"button",onClick:s[6]||(s[6]=a=>S()),class:f(["bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6",[!e.value.payment_gateway||!e.value.payment_gateway_type?"opacity-50 cursor-not-allowed":""]]),disabled:!e.value.payment_gateway||!e.value.payment_gateway_type},{default:c(()=>[n(_(C),{class:"w-4 h-4"}),xe]),_:1},8,["class","disabled"])])):d("",!0),e.value.id&&e.value.payment_gateway&&e.value.payment_gateway.key1_name?(o(),l("div",fe,[n(h,{modelValue:e.value.payment_gateway_key1,"onUpdate:modelValue":s[7]||(s[7]=a=>e.value.payment_gateway_key1=a),error:e.value.errors.payment_gateway_key1,required:"true"},{default:c(()=>[u(m(e.value.payment_gateway.key1_name),1)]),_:1},8,["modelValue","error"])])):d("",!0),e.value.id&&e.value.payment_gateway&&e.value.payment_gateway.key2_name?(o(),l("div",we,[n(h,{modelValue:e.value.payment_gateway_key2,"onUpdate:modelValue":s[8]||(s[8]=a=>e.value.payment_gateway_key2=a),error:e.value.errors.payment_gateway_key2},{default:c(()=>[u(m(e.value.payment_gateway.key2_name),1)]),_:1},8,["modelValue","error"])])):d("",!0),e.value.id&&e.value.payment_gateway&&e.value.payment_gateway.key3_name?(o(),l("div",he,[n(h,{modelValue:e.value.payment_gateway_key3,"onUpdate:modelValue":s[9]||(s[9]=a=>e.value.payment_gateway_key3=a),error:e.value.errors.payment_gateway_key3},{default:c(()=>[u(m(e.value.payment_gateway.key3_name),1)]),_:1},8,["modelValue","error"])])):d("",!0),e.value.id&&e.value.payment_gateway?(o(),l("div",be,[ke,n(b,{modelValue:e.value.payment_gateway_type,"onUpdate:modelValue":s[10]||(s[10]=a=>e.value.payment_gateway_type=a),options:j.value,trackBy:"id",valueProp:"id",label:"id",placeholder:"Select","open-direction":"bottom",class:"mt-1",ref:"multiselect"},null,8,["modelValue","options"]),e.value.errors.payment_gateway_type?(o(),l("div",Ve,m(e.value.errors.payment_gateway_type),1)):d("",!0)])):d("",!0),e.value.id&&e.value.payment_gateway?(o(),l("div",Ce,[n(v,{type:"button",onClick:s[11]||(s[11]=a=>S()),class:f(["bg-green-500 hover:bg-green-600 text-white",[!e.value.payment_gateway||!e.value.payment_gateway_type||!e.value.payment_gateway_key1?"opacity-50 cursor-not-allowed":""]]),disabled:!e.value.payment_gateway||!e.value.payment_gateway_type||!e.value.payment_gateway_key1},{default:c(()=>[n(_(C),{class:"w-4 h-4"}),Oe]),_:1},8,["class","disabled"])])):d("",!0),e.value.id?(o(),l("div",Be,[t("div",$e,[t("div",je,[t("div",Pe,[t("table",Se,[ze,t("tbody",Ae,[(o(!0),l(z,null,A(g.operator.operatorPaymentGateways,(a,w)=>(o(),l("tr",{key:a.id,class:f(w%2===0?void 0:"bg-gray-50")},[t("td",Ge,m(w+1),1),t("td",Ne,m(a.paymentGateway.name),1),t("td",Ue,m(a.type),1),t("td",Te,m(a.key1),1),t("td",Me,[n(v,{class:"bg-red-400 hover:bg-red-500 text-white",onClick:F=>T(a)},{default:c(()=>[n(_(G),{class:"w-4 h-4"})]),_:2},1032,["onClick"])])],2))),128)),g.operator.operatorPaymentGateways.length?d("",!0):(o(),l("tr",Fe,Ee))])])])])])])):d("",!0),e.value.id?(o(),l("div",Re,Je)):d("",!0),e.value.id?(o(),l("div",Ke,[Le,n(b,{modelValue:e.value.vend_id,"onUpdate:modelValue":s[12]||(s[12]=a=>e.value.vend_id=a),options:p.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1",ref:"multiselect"},null,8,["modelValue","options"]),e.value.errors.vend_id?(o(),l("div",Qe,m(e.value.errors.vend_id),1)):d("",!0)])):d("",!0),e.value.id?(o(),l("div",He,[n(v,{type:"button",onClick:s[13]||(s[13]=a=>N()),class:f(["bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6",[e.value.vend_id?"":"opacity-50 cursor-not-allowed"]]),disabled:!e.value.vend_id},{default:c(()=>[n(_(C),{class:"w-4 h-4"}),We]),_:1},8,["class","disabled"])])):d("",!0),e.value.id?(o(),l("div",Xe,[t("div",Ye,[t("div",Ze,[t("div",Ie,[t("table",et,[tt,t("tbody",at,[(o(!0),l(z,null,A(g.operator.vends,(a,w)=>(o(),l("tr",{key:a.id,class:f(w%2===0?void 0:"bg-gray-50")},[t("td",st,m(w+1),1),t("td",ot,m(a.code),1),t("td",lt,[a.latestVendBinding&&a.latestVendBinding.customer?(o(),l("span",nt,[u(m(a.latestVendBinding.customer.code)+" ",1),rt,u(" "+m(a.latestVendBinding.customer.name),1)])):(o(),l("span",dt,m(a.name),1))]),t("td",it,[n(v,{class:"bg-red-400 hover:bg-red-500 text-white",onClick:F=>U(a)},{default:c(()=>[n(_(G),{class:"w-4 h-4"})]),_:2},1032,["onClick"])])],2))),128)),g.operator.vends.length?d("",!0):(o(),l("tr",mt,ut))])])])])])])):d("",!0)]),t("div",pt,[t("div",yt,[n(v,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:s[14]||(s[14]=a=>r.$emit("modalClose")),form:"submit"},{default:c(()=>[n(_(L),{class:"w-4 h-4"}),_t]),_:1}),n(v,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:c(()=>[n(_(Q),{class:"w-4 h-4"}),vt]),_:1})])])],40,Z)]),_:1},8,["open"])]))}};export{$t as default};
